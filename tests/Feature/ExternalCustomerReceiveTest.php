<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Perusahaan;
use App\Models\Customer;
use App\Models\CustomerAttach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalCustomerReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset permission cache
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Clean up multi-connection tables to avoid unique violations
        DB::connection('tako-perusahaan')->table('customers_statuses')->delete();
        DB::connection('tako-customer')->table('customer_attaches')->delete();
        DB::connection('tako-customer')->table('customers')->delete();
        DB::connection('tako-perusahaan')->table('users')->delete();
        DB::connection('tako-perusahaan')->table('perusahaan')->delete();
    }

    public function test_guests_cannot_access_customer_receive_api()
    {
        $response = $this->postJson('/api/customer/receive', []);
        $response->assertStatus(401);
    }

    public function test_unauthorized_users_lacking_permission_cannot_access_customer_receive_api()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', []);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized. Lacking customer.create permission.',
            ]);
    }

    public function test_authorized_users_can_access_without_personal_company()
    {
        // The token user no longer needs to have a company associated with their account to access this endpoint,
        // because the company is mapped via the request body's uid_perusahaan.
        Permission::firstOrCreate(['name' => 'customer.create']);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', []);

        // It should pass the auth guard checks and hit validation for required fields
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['uid', 'uid_marketing', 'uid_perusahaan']);
    }

    public function test_stores_customer_successfully_without_attachments_with_uid_and_jenis_perusahaan()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako Teknologi Indonesia',
            'alamat' => 'Surabaya',
        ]);
        
        $marketingUser = User::factory()->create([
            'uid' => 'MKT-123',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $payload = [
            'uid' => 'CUSTOM_PROJECT_UID_123',
            'uid_marketing' => 'MKT-123',
            'uid_perusahaan' => $perusahaan->uid,
            'jenis_perusahaan' => 'Holding',
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client Company A',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Jl. Genteng Kali No. 10',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Jl. Genteng Kali No. 10',
            'email' => 'client@company.com',
            'top' => '30 Days',
            'status_perpajakan' => 'PKP',
            'no_npwp' => '12.345.678.9-012.000',
            'no_npwp_16' => '1234567890123456',
            'nib' => '9120001234567',
            'nama_pj' => 'John Doe',
            'no_ktp_pj' => '3578123456780001',
            'nama_personal' => 'Jane Smith',
            'email_personal' => 'jane@company.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Customer berhasil disimpan.',
                'data' => [
                    'uid' => 'CUSTOM_PROJECT_UID_123',
                    'jenis_perusahaan' => 'Holding',
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'nama_perusahaan' => 'Client Company A',
            'id_perusahaan' => $perusahaan->id,
            'id_user' => $marketingUser->id,
            'uid' => 'CUSTOM_PROJECT_UID_123',
            'jenis_perusahaan' => 'Holding',
        ], 'tako-customer');

        $customer = Customer::where('nama_perusahaan', 'Client Company A')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('CUSTOM_PROJECT_UID_123', $customer->uid);
        $this->assertEquals('Holding', $customer->jenis_perusahaan);
        $this->assertEquals($marketingUser->id, $customer->id_user);
        $this->assertEquals($perusahaan->id, $customer->id_perusahaan);

        $this->assertDatabaseHas('customers_statuses', [
            'id_Customer' => $customer->id,
            'id_user' => $user->id,
        ], 'tako-perusahaan');
    }

    public function test_stores_customer_successfully_without_optional_jenis_perusahaan()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako Teknologi Indonesia 2',
            'alamat' => 'Surabaya',
        ]);
        
        $marketingUser = User::factory()->create([
            'uid' => 'MKT-456',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $payload = [
            'uid' => 'CUSTOM_PROJECT_UID_456',
            'uid_marketing' => 'MKT-456',
            'uid_perusahaan' => $perusahaan->uid,
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client Company B',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Jl. Genteng Kali No. 10',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Jl. Genteng Kali No. 10',
            'email' => 'clientb@company.com',
            'top' => '30 Days',
            'status_perpajakan' => 'PKP',
            'no_npwp' => '12.345.678.9-012.000',
            'no_npwp_16' => '1234567890123456',
            'nib' => '9120001234567',
            'nama_pj' => 'John Doe',
            'no_ktp_pj' => '3578123456780001',
            'nama_personal' => 'Jane Smith',
            'email_personal' => 'jane@company.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Customer berhasil disimpan.',
                'data' => [
                    'uid' => 'CUSTOM_PROJECT_UID_456',
                    'jenis_perusahaan' => null,
                ]
            ]);

        $this->assertDatabaseHas('customers', [
            'nama_perusahaan' => 'Client Company B',
            'id_perusahaan' => $perusahaan->id,
            'id_user' => $marketingUser->id,
            'uid' => 'CUSTOM_PROJECT_UID_456',
            'jenis_perusahaan' => null,
        ], 'tako-customer');

        $customer = Customer::where('nama_perusahaan', 'Client Company B')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->jenis_perusahaan);
    }

    public function test_validates_required_fields()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $payload = [
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client B',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@b.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['uid', 'uid_marketing', 'uid_perusahaan']);
    }

    public function test_validates_non_existent_uid_marketing()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $payload = [
            'uid' => 'UID_VALID',
            'uid_marketing' => 'NON_EXISTENT_MKT_UID',
            'uid_perusahaan' => $perusahaan->uid,
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client B',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@b.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'uid_marketing' => ['User marketing tidak ditemukan.']
            ]);
    }

    public function test_validates_non_existent_uid_perusahaan()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        User::factory()->create([
            'uid' => 'MKT-999',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $payload = [
            'uid' => 'UID_VALID',
            'uid_marketing' => 'MKT-999',
            'uid_perusahaan' => 'NON_EXISTENT_CO_UID',
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client B',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@b.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'uid_perusahaan' => ['Perusahaan tidak ditemukan.']
            ]);
    }

    public function test_validates_file_format_and_size()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako',
        ]);
        User::factory()->create([
            'uid' => 'MKT-FILE-TEST',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        Storage::fake('customers_external');

        // Test non-pdf file
        $txtFile = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

        $payload = [
            'uid' => 'VALID_UID_FOR_FILE_TEST',
            'uid_marketing' => 'MKT-FILE-TEST',
            'uid_perusahaan' => $perusahaan->uid,
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client B',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@b.com',
            'pdf_npwp' => $txtFile,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_npwp']);

        // Test file too large (> 5MB)
        $largeFile = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'); // 6MB

        $payload['pdf_npwp'] = $largeFile;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_npwp']);
    }

    public function test_stores_customer_with_attachments_successfully()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako',
        ]);
        $marketingUser = User::factory()->create([
            'uid' => 'MKT-ATTACH',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        Storage::fake('customers_external');
        Storage::fake('local');

        $pdfNpwp = UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf');
        $pdfNib = UploadedFile::fake()->create('nib.pdf', 150, 'application/pdf');

        $payload = [
            'uid' => 'ATTACHMENT_TEST_UID',
            'uid_marketing' => 'MKT-ATTACH',
            'uid_perusahaan' => $perusahaan->uid,
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client C',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => 'Surabaya',
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@c.com',
            'no_npwp' => '12.345.678.9-012.000',
            'pdf_npwp' => $pdfNpwp,
            'pdf_nib' => $pdfNib,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Customer berhasil disimpan.',
            ]);

        $customer = Customer::where('nama_perusahaan', 'Client C')->first();
        $this->assertNotNull($customer);

        // Check if attachments are saved in database
        $this->assertDatabaseHas('customer_attaches', [
            'customer_id' => $customer->id,
            'type' => 'npwp',
        ], 'tako-customer');

        $this->assertDatabaseHas('customer_attaches', [
            'customer_id' => $customer->id,
            'type' => 'nib',
        ], 'tako-customer');

        // Check if files are saved in storage
        $companySlug = 'pt-tako';
        $npwpClean = '123456789012000';
        
        $npwpPath = "{$companySlug}/attachment/{$npwpClean}-001-npwp.pdf";
        $nibPath = "{$companySlug}/attachment/{$npwpClean}-002-nib.pdf";

        Storage::disk('customers_external')->assertExists($npwpPath);
        Storage::disk('customers_external')->assertExists($nibPath);

        // Verify the response json has attachment structures
        $responseData = $response->json('data');
        $this->assertCount(2, $responseData['attachments']);
        $this->assertEquals('npwp', $responseData['attachments'][0]['type']);
        $this->assertEquals($npwpPath, $responseData['attachments'][0]['path']);
    }

    public function test_rollback_deletes_uploaded_files_on_failure()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako Fail',
        ]);
        User::factory()->create([
            'uid' => 'MKT-FAIL',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        Storage::fake('customers_external');
        Storage::fake('local');

        $pdfNpwp = UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf');

        $longKota = str_repeat('A', 1000);

        $payload = [
            'uid' => 'ROLLBACK_TEST_UID',
            'uid_marketing' => 'MKT-FAIL',
            'uid_perusahaan' => $perusahaan->uid,
            'kategori_usaha' => 'IT Services',
            'nama_perusahaan' => 'Client D',
            'bentuk_badan_usaha' => 'PT',
            'alamat_lengkap' => 'Surabaya',
            'kota' => $longKota, // passes validation string, but will fail database insert due to size constraint
            'alamat_penagihan' => 'Surabaya',
            'email' => 'client@d.com',
            'no_npwp' => '12.345.678.9-012.000',
            'pdf_npwp' => $pdfNpwp,
        ];

        // Ensure validator accepts kota string length (we didn't define max:255 in validation, only string)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/customer/receive', $payload);

        $response->assertStatus(500);

        // Verify the file was NOT left on disk
        $companySlug = 'pt-tako-fail';
        $npwpClean = '123456789012000';
        $npwpPath = "{$companySlug}/attachment/{$npwpClean}-001-npwp.pdf";

        Storage::disk('customers_external')->assertMissing($npwpPath);
    }

    public function test_accepts_json_payload_with_trailing_comma()
    {
        Permission::firstOrCreate(['name' => 'customer.create']);
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako Tr Comma',
            'alamat' => 'Surabaya',
        ]);
        
        $marketingUser = User::factory()->create([
            'uid' => 'MKT-COMMA',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('customer.create');

        $rawJson = '{
            "uid": "CUSTOM_UID_COMMA",
            "uid_marketing": "MKT-COMMA",
            "uid_perusahaan": "' . $perusahaan->uid . '",
            "jenis_perusahaan": "Holding",
            "kategori_usaha": "IT Services",
            "nama_perusahaan": "Client Comma",
            "bentuk_badan_usaha": "PT",
            "alamat_lengkap": "Surabaya",
            "kota": "Surabaya",
            "alamat_penagihan": "Surabaya",
            "email": "client@comma.com",
            "top": "30 Days",
            "status_perpajakan": "PKP",
            "no_npwp": "12.345.678.9-012.000",
            "no_npwp_16": "1234567890123456",
            "nib": "9120001234567",
            "nama_pj": "John Doe",
            "no_ktp_pj": "3578123456780001",
            "nama_personal": "Jane Smith",
            "email_personal": "jane@company.com",
        }';

        $response = $this->actingAs($user, 'sanctum')
            ->call(
                'POST',
                '/api/customer/receive',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                ],
                $rawJson
            );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Customer berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('customers', [
            'nama_perusahaan' => 'Client Comma',
            'uid' => 'CUSTOM_UID_COMMA',
        ], 'tako-customer');
    }
}
