<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Perusahaan;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use Tests\TestCase;

class CustomerImportNibTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_customer_import_csv_with_blank_nib_is_successful()
    {
        // 1. Setup target company
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako',
            'alamat' => 'Surabaya',
        ]);

        // 2. Setup user with import permission
        $userLogin = User::factory()->create([
            'id_perusahaan' => $perusahaan->id,
            'name' => 'User Login',
        ]);

        Permission::firstOrCreate(['name' => 'customer.import']);
        $userLogin->givePermissionTo('customer.import');

        // Setup a marketing user to avoid fallback warnings/errors if any, or just let it fallback
        $userMarketing = User::factory()->create([
            'name' => 'Marketing Sales',
        ]);
        DB::connection('tako-perusahaan')->table('perusahaan_user_roles')->insert([
            'id_perusahaan' => $perusahaan->id,
            'user_id' => $userMarketing->id,
            'role' => 'marketing',
        ]);

        // 3. Prepare CSV content
        // Headers: namacustomer, jenis_perusahaan, nmmsales, npwp, npwp16, nib
        $csvHeader = "namacustomer,jenis_perusahaan,nmmsales,npwp,npwp16,nib\n";
        // Row 1: Valid NIB
        $row1 = "Cust With Nib,Perusahaan Dalam Negeri,Marketing Sales,123456789012345,1234567890123456,9876543210123\n";
        // Row 2: Empty NIB
        $row2 = "Cust No Nib,Perusahaan Dalam Negeri,Marketing Sales,123456789012345,1234567890123456,\n";
        // Row 3: Missing NPWP/NPWP16 but has NIB (should fail/skip due to missing NPWP)
        $row3 = "Cust Missing Npwp,Perusahaan Dalam Negeri,Marketing Sales,,,9876543210123\n";

        $csvContent = $csvHeader . $row1 . $row2 . $row3;
        $csvFile = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

        // 4. Send request
        $response = $this->actingAs($userLogin)
            ->post(route('customer.import-csv'), [
                'csv_file' => $csvFile,
                'id_perusahaan' => $perusahaan->id,
            ]);

        $response->assertRedirect(route('customer.index'));

        // Check success session message or redirect status
        $response->assertSessionHas('success');
        $response->assertSessionHas('error'); // because Row 3 was skipped

        $errorMsg = session('error');
        $this->assertStringContainsString('data wajib no_npwp, no_npwp_16 kosong untuk Perusahaan Dalam Negeri', $errorMsg);
        $this->assertStringNotContainsString('nib', $errorMsg);

        // 5. Assert database records
        // Cust With Nib should exist with the given NIB
        $custWithNib = Customer::where('nama_perusahaan', 'CUST WITH NIB')->first();
        $this->assertNotNull($custWithNib);
        $this->assertEquals('9876543210123', $custWithNib->nib);

        // Cust No Nib should exist with null/empty NIB
        $custNoNib = Customer::where('nama_perusahaan', 'CUST NO NIB')->first();
        $this->assertNotNull($custNoNib);
        $this->assertNull($custNoNib->nib);

        // Cust Missing Npwp should NOT exist
        $custMissingNpwp = Customer::where('nama_perusahaan', 'CUST MISSING NPWP')->first();
        $this->assertNull($custMissingNpwp);
    }
}
