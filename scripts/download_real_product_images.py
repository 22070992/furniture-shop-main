#!/usr/bin/env python3
import argparse
import json
import os
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageOps


ROOT = Path(__file__).resolve().parents[1]
PUBLIC_PRODUCTS_DIR = ROOT / "public" / "images" / "products"
SOURCE_LOG = ROOT / "storage" / "app" / "real_product_image_sources.json"
CURATED_CACHE_DIR = ROOT / "storage" / "app" / "real_product_sources"
USER_AGENT = "FuremirLocalCatalog/1.0 (+local demo image updater)"

CURATED_BASE = "https://raw.githubusercontent.com/prabinmagar/furniture-add-to-cart-vanilla-js/master/images"
CURATED_ASSETS = {
    "armchair": f"{CURATED_BASE}/armchair.png",
    "bed": f"{CURATED_BASE}/bed.png",
    "bench": f"{CURATED_BASE}/bench.png",
    "bunk-bed": f"{CURATED_BASE}/bunk-bed.png",
    "cabinet": f"{CURATED_BASE}/cabinet.png",
    "card-table": f"{CURATED_BASE}/card-table.png",
    "desk": f"{CURATED_BASE}/desk.png",
    "drawer": f"{CURATED_BASE}/drawer.png",
    "office-chair": f"{CURATED_BASE}/office-chair.png",
    "scissors-chair": f"{CURATED_BASE}/scissors-chair.png",
    "sleeper-sofa": f"{CURATED_BASE}/sleeper-sofa.png",
    "sleigh-bed": f"{CURATED_BASE}/sleigh-bed.png",
    "sofa-bed": f"{CURATED_BASE}/sofa-bed.png",
    "white-armchair": f"{CURATED_BASE}/white-armchair.png",
    "white-sofa": f"{CURATED_BASE}/white-sofa.png",
    "wing-chair": f"{CURATED_BASE}/wing-chair.png",
    "wooden-armchair": f"{CURATED_BASE}/wooden-armchair.png",
    "wooden-dining-table": f"{CURATED_BASE}/wooden-dining-table.png",
    "wooden-table": f"{CURATED_BASE}/wooden-table.png",
    "hanging-chair-stand": "https://img.pictureserver.net/pic_storage/pic/63/b3/undef_src_sa_picid_763805_x_376_type_whitesh_image.jpg?ver=27",
    "hanging-chair-rattan": "https://therattancompany.co.uk/cdn/shop/products/Aurora-Rattan-Hanging-Egg-Chair-The-Rattan-Company.jpg?v=1641933660",
}


TYPE_RULES = [
    ("hanging chair", ["hanging chair", "egg hanging chair", "swing hanging chair"]),
    ("folding chair", ["folding chair"]),
    ("office chair", ["office chair", "task chair", "executive chair"]),
    ("adirondack chair", ["adirondack chair"]),
    ("dining chair", ["dining chair", "cane dining chair"]),
    ("lounge chair", ["lounge chair", "recliner chair", "armchair"]),
    ("accent chair", ["accent chair", "armchair"]),
    ("counter stool", ["counter stool", "bar stool"]),
    ("sofa", ["sofa", "couch", "sectional sofa"]),
    ("bedside cabinet", ["bedside cabinet", "nightstand"]),
    ("nightstand", ["nightstand", "bedside table"]),
    ("queen bed", ["upholstered bed", "queen bed"]),
    ("bed", ["bed frame", "upholstered bed"]),
    ("mattress", ["mattress"]),
    ("headboard", ["rattan headboard", "headboard"]),
    ("dresser", ["dresser furniture", "chest of drawers"]),
    ("drawer chest", ["chest of drawers", "dresser"]),
    ("drawer unit", ["drawer unit", "cabinet drawers"]),
    ("wardrobe", ["wardrobe furniture", "closet wardrobe"]),
    ("vanity desk", ["vanity desk", "dressing table"]),
    ("dining table", ["dining table"]),
    ("coffee table", ["coffee table"]),
    ("nesting table", ["nesting tables"]),
    ("side table", ["side table"]),
    ("console table", ["console table"]),
    ("standing desk", ["standing desk", "electric desk"]),
    ("writing desk", ["writing desk", "desk"]),
    ("meeting table", ["meeting table", "conference table"]),
    ("desk riser", ["desk riser"]),
    ("tv stand", ["tv stand", "media console"]),
    ("media console", ["media console", "tv console"]),
    ("sideboard", ["sideboard buffet", "sideboard cabinet"]),
    ("bar cart", ["bar cart"]),
    ("display cabinet", ["display cabinet"]),
    ("filing cabinet", ["filing cabinet"]),
    ("credenza", ["office credenza", "credenza"]),
    ("bookshelf", ["bookshelf", "bookcase"]),
    ("bookcase", ["bookcase", "bookshelf"]),
    ("shelf", ["wall shelf", "shelving unit"]),
    ("shoe cabinet", ["shoe cabinet"]),
    ("wardrobe organizer", ["wardrobe organizer", "closet organizer"]),
    ("entryway bench", ["entryway bench", "storage bench"]),
    ("storage bench", ["storage bench", "bench furniture"]),
    ("storage ottoman", ["storage ottoman", "ottoman"]),
    ("pantry cabinet", ["pantry cabinet", "kitchen cabinet"]),
    ("cube organizer", ["cube organizer", "storage organizer"]),
    ("bathroom cabinet", ["bathroom cabinet", "linen cabinet"]),
    ("patio dining set", ["patio dining set", "outdoor dining set"]),
    ("patio umbrella", ["patio umbrella"]),
    ("outdoor coffee table", ["outdoor coffee table", "patio coffee table"]),
    ("garden bench", ["garden bench"]),
    ("bistro set", ["bistro set", "balcony bistro set"]),
    ("fire pit table", ["fire pit table"]),
    ("storage box", ["outdoor storage box", "deck box"]),
]


BAD_TITLE_WORDS = {
    "cat",
    "dog",
    "person",
    "people",
    "woman",
    "man",
    "child",
    "coffee cup",
    "breakfast",
    "salad",
    "sprouts",
    "food",
    "statue",
    "portrait",
    "car",
    "motorcycle",
}


GENERIC_QUERY_TOKENS = {
    "black",
    "white",
    "gray",
    "grey",
    "beige",
    "brown",
    "natural",
    "oak",
    "walnut",
    "ivory",
    "charcoal",
    "sage",
    "terracotta",
    "navy",
    "sand",
    "olive",
    "rattan",
    "espresso",
    "furniture",
    "product",
    "photo",
    "image",
}


def slugify(value):
    value = value.lower().strip()
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return value.strip("-")


def curated_asset_key(product_name, category_name, color_name):
    name = product_name.lower()
    color = color_name.lower()

    if "hanging chair" in name:
        if "macrame" in name or "cocoon" in name:
            return "hanging-chair-rattan"
        return "hanging-chair-stand"
    if "folding chair" in name:
        return "scissors-chair"
    if "office chair" in name or "task chair" in name or "executive chair" in name:
        return "office-chair"
    if "dining chair" in name or "cane dining chair" in name:
        return "wooden-armchair"
    if "accent chair" in name or "lounge chair" in name or "recliner" in name or "armchair" in name:
        if "ivory" in color or "white" in color:
            return "white-armchair"
        if "navy" in color or "charcoal" in color:
            return "wing-chair"
        return "armchair"
    if "stool" in name:
        return "wooden-armchair"
    if "sofa" in name or "sectional" in name:
        if "ivory" in color or "sand" in color or "beige" in color:
            return "white-sofa"
        if "navy" in color or "charcoal" in color:
            return "sofa-bed"
        return "sleeper-sofa"
    if "bed" in name and "bedside" not in name:
        if "bunk" in name:
            return "bunk-bed"
        if "sleigh" in name:
            return "sleigh-bed"
        return "bed"
    if "nightstand" in name or "bedside cabinet" in name:
        return "drawer"
    if "mattress" in name:
        return "bed"
    if "headboard" in name:
        return "sleigh-bed"
    if "bench" in name:
        return "bench"
    if "dining table" in name or "meeting table" in name:
        return "wooden-dining-table"
    if "coffee table" in name or "side table" in name or "nesting" in name or "extendable table" in name:
        return "wooden-table"
    if "console table" in name or "desk" in name or "desk riser" in name:
        return "desk"
    if "bar cart" in name:
        return "card-table"
    if "tv stand" in name or "media console" in name or "sideboard" in name or "credenza" in name:
        return "cabinet"
    if (
        "cabinet" in name
        or "dresser" in name
        or "drawer" in name
        or "wardrobe" in name
        or "organizer" in name
        or "storage box" in name
    ):
        return "drawer" if "drawer" in name or "dresser" in name or "chest" in name else "cabinet"
    if "bookcase" in name or "bookshelf" in name or "shelf" in name:
        return None
    if "storage ottoman" in name:
        return "bench"
    if "patio dining set" in name or "bistro" in name:
        return "wooden-dining-table"
    if "adirondack" in name or "outdoor" in name and "chair" in name:
        return "wooden-armchair"
    if "umbrella" in name or "fire pit" in name:
        return None

    category = category_name.lower()
    if category == "bedroom":
        return "bed"
    if category == "dining room":
        return "wooden-dining-table"
    if category == "home office":
        return "desk"
    if category == "storage":
        return "cabinet"
    if category == "outdoor":
        return "bench"
    return "armchair"


def request_json(url):
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "application/json"})
    with urllib.request.urlopen(req, timeout=25) as response:
        return json.loads(response.read().decode("utf-8", "ignore"))


def request_bytes(url):
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "image/*,*/*;q=0.8"})
    with urllib.request.urlopen(req, timeout=35) as response:
        content_type = response.headers.get("Content-Type", "")
        data = response.read()
    return content_type, data


def curated_candidate(row):
    key = curated_asset_key(row["product_name"], row["category_name"], row["color_name"])
    if not key:
        return None
    source_url = CURATED_ASSETS.get(key)
    if not source_url:
        return None
    return {
        "provider": "curated-github-demo",
        "query": key,
        "title": key.replace("-", " "),
        "source_url": source_url,
        "landing_url": "https://github.com/prabinmagar/furniture-add-to-cart-vanilla-js",
        "license": "demo repository asset",
        "score": 100,
    }


def product_queries(product_name, category_name):
    name = product_name.lower()
    candidates = []
    for needle, queries in TYPE_RULES:
        if needle in name:
            candidates.extend(queries)
            break

    if not candidates:
        if category_name.lower() == "living room":
            candidates.append("living room furniture")
        elif category_name.lower() == "bedroom":
            candidates.append("bedroom furniture")
        elif category_name.lower() == "dining room":
            candidates.append("dining room furniture")
        elif category_name.lower() == "home office":
            candidates.append("home office furniture")
        elif category_name.lower() == "storage":
            candidates.append("storage furniture")
        elif category_name.lower() == "outdoor":
            candidates.append("outdoor furniture")
        else:
            candidates.append(category_name)

    unique = []
    for query in candidates:
        query = query.strip()
        if query and query not in unique:
            unique.append(query)
    return unique


def candidate_score(query, item):
    text = " ".join(
        str(item.get(key, "") or "").lower()
        for key in ["title", "creator", "url", "foreign_landing_url", "source"]
    )
    if any(bad in text for bad in BAD_TITLE_WORDS):
        return -50
    score = 0
    tokens = [token for token in re.findall(r"[a-z0-9]+", query.lower()) if len(token) > 2]
    essential_tokens = [token for token in tokens if token not in GENERIC_QUERY_TOKENS]
    essential_matches = 0
    for token in tokens:
        if len(token) > 2 and token in text:
            score += 3
            if token in essential_tokens:
                essential_matches += 1
    if essential_tokens and essential_matches == 0:
        return -25
    if "furniture" in text:
        score += 2
    if any(ext in text for ext in [".jpg", ".jpeg", ".png", ".webp"]):
        score += 1
    return score


def openverse_candidates(query):
    params = urllib.parse.urlencode(
        {
            "q": query,
            "page_size": 20,
            "license_type": "commercial,modification",
        }
    )
    url = f"https://api.openverse.engineering/v1/images/?{params}"
    try:
        data = request_json(url)
    except Exception as exc:
        print(f"openverse failed for {query}: {exc}", file=sys.stderr)
        return []

    results = []
    for item in data.get("results", []):
        image_url = item.get("url") or item.get("thumbnail")
        if not image_url:
            continue
        results.append(
            {
                "provider": "openverse",
                "query": query,
                "title": item.get("title"),
                "source_url": image_url,
                "landing_url": item.get("foreign_landing_url"),
                "license": item.get("license"),
                "score": candidate_score(query, item),
            }
        )
    return sorted(results, key=lambda item: item["score"], reverse=True)


def commons_candidates(query):
    params = urllib.parse.urlencode(
        {
            "action": "query",
            "format": "json",
            "generator": "search",
            "gsrsearch": f"{query} filetype:bitmap",
            "gsrnamespace": 6,
            "gsrlimit": 15,
            "prop": "imageinfo",
            "iiprop": "url|mime|size|extmetadata",
            "iiurlwidth": 1000,
        }
    )
    url = f"https://commons.wikimedia.org/w/api.php?{params}"
    try:
        data = request_json(url)
    except Exception as exc:
        print(f"commons failed for {query}: {exc}", file=sys.stderr)
        return []

    results = []
    for page in data.get("query", {}).get("pages", {}).values():
        info = (page.get("imageinfo") or [{}])[0]
        image_url = info.get("thumburl") or info.get("url")
        if not image_url:
            continue
        title = page.get("title", "")
        results.append(
            {
                "provider": "wikimedia-commons",
                "query": query,
                "title": title,
                "source_url": image_url,
                "landing_url": info.get("descriptionurl"),
                "license": (info.get("extmetadata", {}).get("LicenseShortName", {}) or {}).get("value"),
                "score": candidate_score(query, {"title": title, "url": image_url, "source": "furniture"}),
            }
        )
    return sorted(results, key=lambda item: item["score"], reverse=True)


def get_candidates(query, cache):
    if query not in cache:
        candidates = openverse_candidates(query)
        if len([item for item in candidates if item["score"] > 0]) < 3:
            candidates.extend(commons_candidates(query))
        cache[query] = sorted(candidates, key=lambda item: item["score"], reverse=True)
        time.sleep(0.3)
    return cache[query]


def load_rows(limit=None):
    sql = """
        SELECT p.product_id, p.name, c.name AS category_name, pd.sku, co.name AS color_name, co.code, pi.url
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        JOIN product_details pd ON pd.product_id = p.product_id
        JOIN colors co ON co.color_id = pd.color_id
        JOIN product_images pi ON pi.sku = pd.sku
        ORDER BY p.product_id, pd.sku
    """
    raw = subprocess.check_output(["mysql", "-uroot", "-N", "-B", "furniture_shop", "-e", sql], cwd=ROOT)
    rows = []
    for line in raw.decode("utf-8").splitlines():
        product_id, product_name, category_name, sku, color_name, color_hex, url = line.split("\t")
        rows.append(
            {
                "product_id": int(product_id),
                "product_name": product_name,
                "category_name": category_name,
                "sku": sku,
                "color_name": color_name,
                "color_hex": color_hex,
                "url": url,
                "filename": Path(urllib.parse.urlparse(url).path).name,
            }
        )
    return rows[:limit] if limit else rows


def parse_hex_color(value):
    value = (value or "").strip().lstrip("#")
    if len(value) != 6:
        return None
    try:
        return tuple(int(value[index : index + 2], 16) for index in (0, 2, 4))
    except ValueError:
        return None


def normalize_and_save(data, destination, tint_hex=None, tint_strength=0.18):
    source = ImageOps.exif_transpose(Image.open(BytesIO(data)))
    alpha = None
    if source.mode in ("RGBA", "LA") or ("transparency" in source.info):
        source = source.convert("RGBA")
        alpha = source.getchannel("A")
        image = source.convert("RGB")
    else:
        image = source.convert("RGB")

    tint = parse_hex_color(tint_hex)
    if tint and alpha is not None:
        overlay = Image.new("RGB", image.size, tint)
        tinted = Image.blend(image, overlay, tint_strength)
        image.paste(tinted, mask=alpha)

    if alpha is not None:
        matte = Image.new("RGB", image.size, "white")
        matte.paste(image, mask=alpha)
        image = matte

    width, height = image.size
    if width < 250 or height < 250:
        raise ValueError(f"image too small: {width}x{height}")
    contained = ImageOps.contain(image, (820, 820), Image.Resampling.LANCZOS)
    canvas = Image.new("RGB", (900, 900), "white")
    canvas.paste(contained, ((900 - contained.width) // 2, (900 - contained.height) // 2))
    canvas.save(destination, "JPEG", quality=90, optimize=True)


def download_for_row(row, cache, used_urls, dry_run=False):
    destination = PUBLIC_PRODUCTS_DIR / row["filename"]
    curated = curated_candidate(row)
    if curated:
        try:
            if dry_run:
                return {**curated, "local_file": str(destination), "dry_run": True}
            CURATED_CACHE_DIR.mkdir(parents=True, exist_ok=True)
            cache_file = CURATED_CACHE_DIR / f"{curated['query']}.img"
            if cache_file.exists():
                data = cache_file.read_bytes()
            else:
                content_type, data = request_bytes(curated["source_url"])
                cache_file.write_bytes(data)
            if len(data) < 8000:
                raise ValueError(f"curated image too small: {len(data)} bytes")
            normalize_and_save(data, destination, tint_hex=row.get("color_hex"), tint_strength=0.2)
            return {**curated, "local_file": str(destination)}
        except Exception as exc:
            print(f"curated failed for {row['sku']}: {exc}", file=sys.stderr)

    queries = product_queries(row["product_name"], row["category_name"])
    # Try product type first, then a color-specific query if enough open images exist.
    query_queue = []
    for query in queries:
        query_queue.append(f"{row['color_name']} {query}")
        query_queue.append(query)

    failures = []
    for query in query_queue:
        for candidate in get_candidates(query, cache):
            if candidate["score"] < -10:
                continue
            source_url = candidate["source_url"]
            if source_url in used_urls and len(used_urls) < 500:
                continue
            try:
                if dry_run:
                    return {**candidate, "local_file": str(destination), "dry_run": True}
                content_type, data = request_bytes(source_url)
                if "html" in content_type.lower() or len(data) < 8000:
                    raise ValueError(f"not an image response: {content_type}, {len(data)} bytes")
                normalize_and_save(data, destination)
                used_urls.add(source_url)
                return {**candidate, "local_file": str(destination)}
            except Exception as exc:
                failures.append(f"{source_url}: {exc}")
                continue
    raise RuntimeError("; ".join(failures[-3:]) or "no usable candidates")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    PUBLIC_PRODUCTS_DIR.mkdir(parents=True, exist_ok=True)
    rows = load_rows(args.limit)
    cache = {}
    used_urls = set()
    source_log = []
    failed = []

    for index, row in enumerate(rows, start=1):
        label = f"{index}/{len(rows)} {row['sku']} {row['product_name']} ({row['color_name']})"
        try:
            source = download_for_row(row, cache, used_urls, dry_run=args.dry_run)
            source_log.append({**row, **source})
            print(f"OK {label} <- {source['provider']} :: {source.get('title')}")
        except Exception as exc:
            failed.append({**row, "error": str(exc)})
            print(f"FAIL {label}: {exc}", file=sys.stderr)

    SOURCE_LOG.parent.mkdir(parents=True, exist_ok=True)
    SOURCE_LOG.write_text(
        json.dumps({"sources": source_log, "failed": failed}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    print(f"done: {len(source_log)} downloaded, {len(failed)} failed")
    if failed:
        sys.exit(1)


if __name__ == "__main__":
    main()
