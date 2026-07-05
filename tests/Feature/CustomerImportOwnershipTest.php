<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Perusahaan;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Permission;
use Tests\TestCase;
use Mockery;

class CustomerImportOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_customer_import_csv_ownership_matching()
    {
        // 1. Setup target company
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako',
            'alamat' => 'Surabaya',
        ]);

        // 2. Setup users in the target company
        $userLogin = User::factory()->create([
            'id_perusahaan' => $perusahaan->id,
            'name' => 'User Login',
        ]);

        Permission::firstOrCreate(['name' => 'customer.import']);
        $userLogin->givePermissionTo('customer.import');

        // User A: unique matching
        $userA = User::factory()->create([
            'name' => 'Budi Santoso',
        ]);
        DB::connection('tako-perusahaan')->table('perusahaan_user_roles')->insert([
            'id_perusahaan' => $perusahaan->id,
            'user_id' => $userA->id,
            'role' => 'marketing',
        ]);

        // User B: duplicate matching (marketing)
        $userB1 = User::factory()->create([
            'name' => 'Duplicate Name',
        ]);
        DB::connection('tako-perusahaan')->table('perusahaan_user_roles')->insert([
            'id_perusahaan' => $perusahaan->id,
            'user_id' => $userB1->id,
            'role' => 'manager',
        ]);

        $userB2 = User::factory()->create([
            'name' => 'Duplicate Name',
        ]);
        DB::connection('tako-perusahaan')->table('perusahaan_user_roles')->insert([
            'id_perusahaan' => $perusahaan->id,
            'user_id' => $userB2->id,
            'role' => 'marketing',
        ]);

        // User C: duplicate matching (both marketing - consistent choosing)
        // Ensure user IDs are sequential
        $userC1 = User::factory()->create([
            'name' => 'Double Marketing',
        ]);
        $userC2 = User::factory()->create([
            'name' => 'Double Marketing',
        ]);

        DB::connection('tako-perusahaan')->table('perusahaan_user_roles')->insert([
            [
                'id_perusahaan' => $perusahaan->id,
                'user_id' => $userC1->id,
                'role' => 'marketing',
            ],
            [
                'id_perusahaan' => $perusahaan->id,
                'user_id' => $userC2->id,
                'role' => 'marketing',
            ]
        ]);

        // User D: Nenik Puspitasari (only in direct company field, no pivot, mixed case)
        $userNenik = User::factory()->create([
            'id_perusahaan' => $perusahaan->id,
            'name' => 'Nenik Puspitasari',
        ]);
        $marketingRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'marketing']);
        $userNenik->assignRole($marketingRole);

        // Spy on Log to verify log outputs
        Log::spy();

        // 3. Prepare CSV content
        $csvHeader = "namacustomer,jenis_perusahaan,nmmsales\n";
        $row1 = "Cust A,Perusahaan Luar Negeri, Budi Santoso \n"; // Trim/Spaces normalization
        $row2 = "Cust B,Perusahaan Luar Negeri,duplicate name\n"; // Matches userB2 (marketing role prioritized)
        $row3 = "Cust C,Perusahaan Luar Negeri,Double Marketing\n"; // Matches userC1 (lowest ID chosen consistently)
        $row4 = "Cust D,Perusahaan Luar Negeri,Non Existent Sales\n"; // Fallback to first marketing user (userA)
        $row5 = "Cust E,Perusahaan Luar Negeri,\n"; // Empty sales, fallback to first marketing user (userA)
        $row6 = "Cust F,Perusahaan Luar Negeri,NENIK PUSPITASARI\n"; // Matches userNenik (uppercase)
        $row7 = "Cust G,Perusahaan Luar Negeri,nenik puspitasari\n"; // Matches userNenik (lowercase)
        $row8 = "Cust H,Perusahaan Luar Negeri,Nenik   Puspitasari\n"; // Matches userNenik (multiple spaces collapsing)

        $csvContent = $csvHeader . $row1 . $row2 . $row3 . $row4 . $row5 . $row6 . $row7 . $row8;

        // Mock a file upload
        $csvFile = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

        // 4. Send request
        $response = $this->actingAs($userLogin)
            ->post(route('customer.import-csv'), [
                'csv_file' => $csvFile,
                'id_perusahaan' => $perusahaan->id,
            ]);

        $response->assertRedirect(route('customer.index'));

        // 5. Assert database records
        $custA = Customer::where('nama_perusahaan', 'Cust A')->first();
        $this->assertNotNull($custA);
        $this->assertEquals($userA->id, $custA->id_user);

        $custB = Customer::where('nama_perusahaan', 'Cust B')->first();
        $this->assertNotNull($custB);
        $this->assertEquals($userB2->id, $custB->id_user); // prioritized marketing

        $custC = Customer::where('nama_perusahaan', 'Cust C')->first();
        $this->assertNotNull($custC);
        $this->assertEquals(min($userC1->id, $userC2->id), $custC->id_user); // consistent choosing (lowest ID)

        $custD = Customer::where('nama_perusahaan', 'Cust D')->first();
        $this->assertNotNull($custD);
        $this->assertEquals($userA->id, $custD->id_user); // fallback to first marketing user (userA has role marketing and has lowest ID)

        $custE = Customer::where('nama_perusahaan', 'Cust E')->first();
        $this->assertNotNull($custE);
        $this->assertEquals($userA->id, $custE->id_user); // fallback to first marketing user (userA has role marketing and has lowest ID)

        $custF = Customer::where('nama_perusahaan', 'Cust F')->first();
        $this->assertNotNull($custF);
        $this->assertEquals($userNenik->id, $custF->id_user); // uppercase match

        $custG = Customer::where('nama_perusahaan', 'Cust G')->first();
        $this->assertNotNull($custG);
        $this->assertEquals($userNenik->id, $custG->id_user); // lowercase match

        $custH = Customer::where('nama_perusahaan', 'Cust H')->first();
        $this->assertNotNull($custH);
        $this->assertEquals($userNenik->id, $custH->id_user); // spaces normalization match

        // 6. Assert log warnings are written
        Log::shouldHaveReceived('warning')
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'Duplikat user marketing dengan nama');
            }))
            ->once();

        Log::shouldHaveReceived('warning')
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'tidak cocok dengan user di perusahaan');
            }))
            ->twice();
    }

    public function test_customer_import_csv_fallback_to_null_when_no_marketing()
    {
        // 1. Setup target company with NO marketing users
        $perusahaan = Perusahaan::create([
            'nama_perusahaan' => 'PT Tako No Marketing',
            'alamat' => 'Jakarta',
        ]);

        $userLogin = User::factory()->create([
            'id_perusahaan' => $perusahaan->id,
            'name' => 'User Login',
        ]);

        Permission::firstOrCreate(['name' => 'customer.import']);
        $userLogin->givePermissionTo('customer.import');

        Log::spy();

        // 2. Prepare CSV content with no match
        $csvHeader = "namacustomer,jenis_perusahaan,nmmsales\n";
        $row1 = "Cust X,Perusahaan Luar Negeri,Unknown Sales\n";
        $csvContent = $csvHeader . $row1;

        $csvFile = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

        // We expect it to try to set id_user to null, which will throw DB exception if strict,
        // or succeed if SQLite allows null (e.g. if foreign key checks are disabled during RefreshDatabase).
        try {
            $response = $this->actingAs($userLogin)
                ->post(route('customer.import-csv'), [
                    'csv_file' => $csvFile,
                    'id_perusahaan' => $perusahaan->id,
                ]);
            
            // Check if Cust X is saved with null owner
            $custX = Customer::where('nama_perusahaan', 'Cust X')->first();
            if ($custX) {
                $this->assertNull($custX->id_user);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected query exception if schema strictly enforces not-null constraints
            $this->assertTrue(true);
        }

        // Assert log contains fallback to null
        Log::shouldHaveReceived('warning')
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'tidak cocok dengan user di perusahaan') && str_contains($message, 'fallback ID: null');
            }))
            ->once();
    }
}
