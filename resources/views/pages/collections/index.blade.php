@extends('layouts.app')

@section('content')
    <div class="room-collection-index pt-80 pb-100" data-feedback-id="collections.index"
        data-component="RoomCollectionsIndex">
        <div class="container">
            <div class="breadcrumb-content mb-30">
                <ul>
                    <li><a href="{{ route('user') }}">Home</a></li>
                    <li>Room Collections</li>
                </ul>
            </div>
            <div class="section-title-2 mb-45">
                <h2>Room Collections</h2>
            </div>
            <div class="row">
                @foreach ($collections as $collection)
                    <div class="col-lg-3 col-md-6 col-12">
                        <a class="room-collection-card" href="{{ $collection->publicUrl() }}"
                            data-feedback-id="collections.card.{{ $collection->slug }}">
                            <span>{{ $collection->title }}</span>
                            <small>{{ \Illuminate\Support\Str::limit($collection->description, 100) }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
