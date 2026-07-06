<?php

namespace Tests\Feature;

use App\Models\ProductDetail;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportDetails;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminReceiptViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPermissionTables();

        Gate::before(static fn () => true);

        $admin = new User();
        $admin->user_id = 1;
        $admin->first_name = 'Admin';
        $admin->last_name = 'User';
        $admin->email = 'admin@example.test';
        $admin->avatar = '/images/default/default_avatar.jpg';
        $this->be($admin);
    }

    public function test_receipt_index_renders_without_employee_default_address(): void
    {
        $receipt = $this->receiptWithMissingEmployeeAddress();

        $html = view('admin.receipts.index', [
            'receipts' => new LengthAwarePaginator(collect([$receipt]), 1, 7),
            'suppliers' => collect([$receipt->supplier]),
            'search' => null,
            'type' => null,
            'sort' => null,
        ])->render();

        $this->assertStringContainsString('Receipts Management', $html);
        $this->assertStringContainsString('No default address', $html);
    }

    public function test_receipt_details_renders_with_missing_product_relations(): void
    {
        $receipt = $this->receiptWithMissingEmployeeAddress();

        $detail = new ReceivingReportDetails();
        $detail->receiving_report_id = $receipt->receiving_report_id;
        $detail->sku = 'missing-sku';
        $detail->quantities = 2;
        $detail->unit_price = 100000;
        $detail->setRelation('detailed_product', null);

        $product = new ProductDetail();
        $product->sku = 'sku-without-color';
        $product->name = 'Product Without Color';
        $product->size = '80x80x80 cm';
        $product->setRelation('images', collect());
        $product->setRelation('color', null);

        $html = view('admin.receipts.receipts_details', [
            'page' => 'Receipt Details',
            'receipt' => $receipt,
            'detailed_receipts' => new LengthAwarePaginator(collect([$detail]), 1, 5),
            'detailed_products' => new LengthAwarePaginator(collect([$product]), 1, 5),
        ])->render();

        $this->assertStringContainsString('Receipt Details', $html);
        $this->assertStringContainsString('Deleted or missing product', $html);
        $this->assertStringContainsString('No color', $html);
    }

    private function receiptWithMissingEmployeeAddress(): ReceivingReport
    {
        $supplier = new Supplier();
        $supplier->supplier_id = 10;
        $supplier->name = 'Demo Supplier';
        $supplier->phone_number = '0900000000';
        $supplier->address = 'Demo address';

        $employee = new User();
        $employee->user_id = 11;
        $employee->first_name = 'No';
        $employee->last_name = 'Address';
        $employee->email = 'employee@example.test';
        $employee->setRelation('default_address', null);

        $receipt = new ReceivingReport();
        $receipt->receiving_report_id = 12;
        $receipt->total_price = 200000;
        $receipt->created_by = $employee->user_id;
        $receipt->supplier_id = $supplier->supplier_id;
        $receipt->setRelation('supplier', $supplier);
        $receipt->setRelation('employee', $employee);

        return $receipt;
    }

    private function createPermissionTables(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
    }
}
