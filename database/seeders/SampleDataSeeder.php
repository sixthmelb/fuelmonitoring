<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\Unit;
use App\Models\FuelTransaction;
use Carbon\Carbon;

/**
 * Command untuk membuat seeder ini:
 * php artisan make:seeder SampleDataSeeder
 * 
 * Untuk menjalankan seeder:
 * php artisan db:seed --class=SampleDataSeeder
 * 
 * Seeder ini akan create sample data untuk testing aplikasi
 */
class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating sample fuel storages...');
        $this->createFuelStorages();

        $this->command->info('Creating sample fuel trucks...');
        $this->createFuelTrucks();

        $this->command->info('Creating sample units...');
        $this->createUnits();

        $this->command->info('Creating sample transactions...');
        $this->createSampleTransactions();

        $this->command->info('Sample data created successfully!');
    }

    /**
     * Create sample fuel storages
     */
    private function createFuelStorages(): void
    {
        $storages = [
            [
                'name' => 'Main Fuel Storage A',
                'code' => 'ST-001',
                'location' => 'Mining Site A - North',
                'max_capacity' => 50000.00,
                'current_capacity' => 35000.00,
                'min_threshold' => 5000.00,
                'description' => 'Primary fuel storage for north mining operations',
                'is_active' => true,
            ],
            [
                'name' => 'Secondary Fuel Storage B',
                'code' => 'ST-002',
                'location' => 'Mining Site B - South',
                'max_capacity' => 30000.00,
                'current_capacity' => 18000.00,
                'min_threshold' => 3000.00,
                'description' => 'Secondary fuel storage for south mining operations',
                'is_active' => true,
            ],
        ];

        foreach ($storages as $storage) {
            FuelStorage::create($storage);
        }
    }

    /**
     * Create sample fuel trucks
     */
    private function createFuelTrucks(): void
    {
        $trucks = [
            [
                'name' => 'Fuel Truck Alpha',
                'code' => 'FT-001',
                'license_plate' => 'B 1234 ABC',
                'driver_name' => 'Ahmad Fauzi',
                'driver_phone' => '081234567890',
                'max_capacity' => 5000.00,
                'current_capacity' => 3200.00,
                'status' => 'available',
                'last_maintenance' => Carbon::now()->subDays(15),
                'is_active' => true,
            ],
            [
                'name' => 'Fuel Truck Beta',
                'code' => 'FT-002',
                'license_plate' => 'B 5678 DEF',
                'driver_name' => 'Budi Santoso',
                'driver_phone' => '081234567891',
                'max_capacity' => 5000.00,
                'current_capacity' => 0.00,
                'status' => 'available',
                'last_maintenance' => Carbon::now()->subDays(8),
                'is_active' => true,
            ],
            [
                'name' => 'Fuel Truck Gamma',
                'code' => 'FT-003',
                'license_plate' => 'B 9012 GHI',
                'driver_name' => 'Charlie Wijaya',
                'driver_phone' => '081234567892',
                'max_capacity' => 4000.00,
                'current_capacity' => 2500.00,
                'status' => 'in_use',
                'last_maintenance' => Carbon::now()->subDays(25),
                'is_active' => true,
            ],
        ];

        foreach ($trucks as $truck) {
            FuelTruck::create($truck);
        }
    }

    /**
     * Create sample units
     */
    private function createUnits(): void
    {
        $units = [
            // Dump Trucks
            [
                'code' => 'DT-001',
                'name' => 'Dump Truck CAT 777D',
                'type' => 'dump_truck',
                'brand' => 'Caterpillar',
                'model' => '777D',
                'year' => 2018,
                'engine_capacity' => 27000.00,
                'fuel_tank_capacity' => 1500.00,
                'current_km' => 125420.50,
                'current_hm' => 8945.25,
                'last_service_km' => 124000.00,
                'last_service_hm' => 8800.00,
                'operator_name' => 'Dedi Kurniawan',
                'location' => 'North Pit',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'code' => 'DT-002',
                'name' => 'Dump Truck CAT 777F',
                'type' => 'dump_truck',
                'brand' => 'Caterpillar',
                'model' => '777F',
                'year' => 2020,
                'engine_capacity' => 27000.00,
                'fuel_tank_capacity' => 1500.00,
                'current_km' => 89650.25,
                'current_hm' => 6234.75,
                'last_service_km' => 88000.00,
                'last_service_hm' => 6100.00,
                'operator_name' => 'Eko Prasetyo',
                'location' => 'South Pit',
                'status' => 'active',
                'is_active' => true,
            ],
            // Excavators
            [
                'code' => 'EX-001',
                'name' => 'Excavator CAT 390F',
                'type' => 'excavator',
                'brand' => 'Caterpillar',
                'model' => '390F',
                'year' => 2019,
                'engine_capacity' => 18100.00,
                'fuel_tank_capacity' => 1200.00,
                'current_km' => 0.00, // Excavator biasanya tidak pakai KM
                'current_hm' => 5687.50,
                'last_service_km' => 0.00,
                'last_service_hm' => 5500.00,
                'operator_name' => 'Fajar Hidayat',
                'location' => 'North Pit',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'code' => 'EX-002',
                'name' => 'Excavator Komatsu PC800',
                'type' => 'excavator',
                'brand' => 'Komatsu',
                'model' => 'PC800',
                'year' => 2017,
                'engine_capacity' => 19500.00,
                'fuel_tank_capacity' => 1100.00,
                'current_km' => 0.00,
                'current_hm' => 7892.25,
                'last_service_km' => 0.00,
                'last_service_hm' => 7700.00,
                'operator_name' => 'Gunawan Saputra',
                'location' => 'South Pit',
                'status' => 'maintenance',
                'is_active' => true,
            ],
            // Dozer
            [
                'code' => 'DZ-001',
                'name' => 'Dozer CAT D9T',
                'type' => 'dozer',
                'brand' => 'Caterpillar',
                'model' => 'D9T',
                'year' => 2019,
                'engine_capacity' => 20900.00,
                'fuel_tank_capacity' => 950.00,
                'current_km' => 0.00,
                'current_hm' => 4521.75,
                'last_service_km' => 0.00,
                'last_service_hm' => 4400.00,
                'operator_name' => 'Hendra Wijaya',
                'location' => 'Waste Dump Area',
                'status' => 'active',
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }

    /**
     * Create sample transactions
     */
    private function createSampleTransactions(): void
    {
        $storage1 = FuelStorage::where('code', 'ST-001')->first();
        $storage2 = FuelStorage::where('code', 'ST-002')->first();
        $truck1 = FuelTruck::where('code', 'FT-001')->first();
        $truck2 = FuelTruck::where('code', 'FT-002')->first();
        $truck3 = FuelTruck::where('code', 'FT-003')->first();
        
        $unit1 = Unit::where('code', 'DT-001')->first();
        $unit2 = Unit::where('code', 'DT-002')->first();
        $unit3 = Unit::where('code', 'EX-001')->first();
        $unit4 = Unit::where('code', 'EX-002')->first();
        $unit5 = Unit::where('code', 'DZ-001')->first();

        $superadmin = \App\Models\User::where('email', 'superadmin@fuelmonitor.com')->first();
        $manager = \App\Models\User::where('email', 'manager@fuelmonitor.com')->first();
        $staff = \App\Models\User::where('email', 'staff@fuelmonitor.com')->first();

        // Sample transactions untuk minggu terakhir
        $transactions = [
            // Day 1 - Vendor supply ke storage
            [
                'transaction_type' => FuelTransaction::TYPE_VENDOR_TO_STORAGE,
                'destination_storage_id' => $storage1->id,
                'fuel_amount' => 15000.00,
                'transaction_date' => Carbon::now()->subDays(7)->hour(8),
                'notes' => 'Weekly fuel supply from PT. Pertamina',
                'created_by' => $manager->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_VENDOR_TO_STORAGE,
                'destination_storage_id' => $storage2->id,
                'fuel_amount' => 12000.00,
                'transaction_date' => Carbon::now()->subDays(7)->hour(9),
                'notes' => 'Weekly fuel supply from PT. Shell',
                'created_by' => $manager->id,
                'is_approved' => true,
            ],

            // Day 2 - Storage ke truck
            [
                'transaction_type' => FuelTransaction::TYPE_STORAGE_TO_TRUCK,
                'source_storage_id' => $storage1->id,
                'destination_truck_id' => $truck1->id,
                'fuel_amount' => 5000.00,
                'transaction_date' => Carbon::now()->subDays(6)->hour(7),
                'notes' => 'Filling truck for field operations',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_STORAGE_TO_TRUCK,
                'source_storage_id' => $storage2->id,
                'destination_truck_id' => $truck3->id,
                'fuel_amount' => 4000.00,
                'transaction_date' => Carbon::now()->subDays(6)->hour(8),
                'notes' => 'Filling truck for south area operations',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],

            // Day 3 - Direct storage to unit dan truck to unit
            [
                'transaction_type' => FuelTransaction::TYPE_STORAGE_TO_UNIT,
                'source_storage_id' => $storage1->id,
                'unit_id' => $unit1->id,
                'fuel_amount' => 800.00,
                'unit_km' => 125420.50,
                'unit_hm' => 8945.25,
                'transaction_date' => Carbon::now()->subDays(5)->hour(6),
                'notes' => 'Direct refueling DT-001 at storage',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck1->id,
                'unit_id' => $unit2->id,
                'fuel_amount' => 750.00,
                'unit_km' => 89650.25,
                'unit_hm' => 6234.75,
                'transaction_date' => Carbon::now()->subDays(5)->hour(10),
                'notes' => 'Field refueling DT-002',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck3->id,
                'unit_id' => $unit3->id,
                'fuel_amount' => 650.00,
                'unit_km' => 0.00, // Excavator tidak pakai KM
                'unit_hm' => 5687.50,
                'transaction_date' => Carbon::now()->subDays(5)->hour(14),
                'notes' => 'Field refueling EX-001',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],

            // Day 4 - More field operations
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck1->id,
                'unit_id' => $unit5->id,
                'fuel_amount' => 500.00,
                'unit_km' => 0.00,
                'unit_hm' => 4521.75,
                'transaction_date' => Carbon::now()->subDays(4)->hour(9),
                'notes' => 'Field refueling DZ-001',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_STORAGE_TO_UNIT,
                'source_storage_id' => $storage2->id,
                'unit_id' => $unit4->id,
                'fuel_amount' => 600.00,
                'unit_km' => 0.00,
                'unit_hm' => 7892.25,
                'transaction_date' => Carbon::now()->subDays(4)->hour(11),
                'notes' => 'Refueling before maintenance',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],

            // Day 5 - Previous transactions untuk calculate consumption
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck1->id,
                'unit_id' => $unit1->id,
                'fuel_amount' => 820.00,
                'unit_km' => 125250.00, // Previous KM untuk consumption calculation
                'unit_hm' => 8920.00,   // Previous HM
                'transaction_date' => Carbon::now()->subDays(3)->hour(8),
                'notes' => 'Previous refueling DT-001 for consumption tracking',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck3->id,
                'unit_id' => $unit2->id,
                'fuel_amount' => 780.00,
                'unit_km' => 89500.00, // Previous KM
                'unit_hm' => 6200.00,  // Previous HM
                'transaction_date' => Carbon::now()->subDays(3)->hour(13),
                'notes' => 'Previous refueling DT-002 for consumption tracking',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],

            // Day 6 - Recent transactions
            [
                'transaction_type' => FuelTransaction::TYPE_STORAGE_TO_TRUCK,
                'source_storage_id' => $storage1->id,
                'destination_truck_id' => $truck2->id,
                'fuel_amount' => 5000.00,
                'transaction_date' => Carbon::now()->subDays(2)->hour(7),
                'notes' => 'Filling empty truck FT-002',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck2->id,
                'unit_id' => $unit3->id,
                'fuel_amount' => 700.00,
                'unit_km' => 0.00,
                'unit_hm' => 5650.00, // Previous HM untuk consumption
                'transaction_date' => Carbon::now()->subDays(2)->hour(15),
                'notes' => 'Field refueling EX-001',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],

            // Day 7 - Today's transactions
            [
                'transaction_type' => FuelTransaction::TYPE_TRUCK_TO_UNIT,
                'source_truck_id' => $truck2->id,
                'unit_id' => $unit5->id,
                'fuel_amount' => 520.00,
                'unit_km' => 0.00,
                'unit_hm' => 4500.00, // Previous HM
                'transaction_date' => Carbon::now()->hour(8),
                'notes' => 'Morning refueling DZ-001',
                'created_by' => $staff->id,
                'is_approved' => true,
            ],
        ];

        foreach ($transactions as $transactionData) {
            // Bypass observer untuk sample data supaya tidak conflict dengan capacity
            $transaction = new FuelTransaction();
            $transaction->fill($transactionData);
            $transaction->transaction_code = FuelTransaction::generateTransactionCode();
            $transaction->saveQuietly(); // saveQuietly supaya tidak trigger observer
        }

        // Update current capacities manually untuk sample data
        $storage1->updateQuietly(['current_capacity' => 35000.00]);
        $storage2->updateQuietly(['current_capacity' => 18000.00]);
        $truck1->updateQuietly(['current_capacity' => 3200.00]);
        $truck2->updateQuietly(['current_capacity' => 3300.00]);
        $truck3->updateQuietly(['current_capacity' => 2500.00]);
    }
}