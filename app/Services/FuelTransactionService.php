<?php

namespace App\Services;

use App\Models\FuelTransaction;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\Unit;
use App\Models\User;
use App\Models\ApprovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Command untuk membuat service ini:
 * php artisan make:class Services/FuelTransactionService
 * 
 * Service ini menangani semua business logic untuk fuel transactions:
 * - Create transaction dengan validasi
 * - Calculate consumption
 * - Generate reports
 * - Handle approval requests
 */
class FuelTransactionService
{
    /**
     * Create new fuel transaction dengan validasi lengkap
     */
    public function createTransaction(array $data, User $user): array
    {
        try {
            DB::beginTransaction();

            // Validate input data
            $validation = $this->validateTransactionData($data);
            if (!$validation['success']) {
                return $validation;
            }

            // Prepare transaction data
            $transactionData = $this->prepareTransactionData($data, $user);

            // Create transaction
            $transaction = FuelTransaction::create($transactionData);

            // Calculate consumption jika ada data sebelumnya
            $consumption = $this->calculateConsumption($transaction);

            DB::commit();

            return [
                'success' => true,
                'data' => $transaction,
                'consumption' => $consumption,
                'message' => 'Transaction created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create fuel transaction', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update existing transaction dengan approval jika diperlukan
     */
    public function updateTransaction(FuelTransaction $transaction, array $data, User $user): array
    {
        try {
            // Check if transaction can be edited
            if (!$transaction->canBeEdited()) {
                return [
                    'success' => false,
                    'message' => 'Transaction cannot be edited'
                ];
            }

            // Check if requires approval
            if ($transaction->requiresApprovalForEdit()) {
                return $this->createApprovalRequest($transaction, $data, $user, 'edit');
            }

            DB::beginTransaction();

            // Validate new data
            $validation = $this->validateTransactionData($data);
            if (!$validation['success']) {
                return $validation;
            }

            // Update transaction
            $transaction->update($data);

            DB::commit();

            return [
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction updated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update fuel transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update transaction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete transaction dengan approval jika diperlukan
     */
    public function deleteTransaction(FuelTransaction $transaction, User $user, string $reason): array
    {
        try {
            // Check if requires approval
            if ($transaction->requiresApprovalForEdit()) {
                return $this->createApprovalRequest($transaction, [], $user, 'delete', $reason);
            }

            DB::beginTransaction();

            $transaction->delete();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete fuel transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete transaction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create approval request
     */
    private function createApprovalRequest(FuelTransaction $transaction, array $newData, User $user, string $type, string $reason = ''): array
    {
        try {
            $approvalData = [
                'fuel_transaction_id' => $transaction->id,
                'request_type' => $type,
                'requested_by' => $user->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'reason' => $reason,
                'original_data' => $transaction->toArray(),
            ];

            if ($type === 'edit') {
                $approvalData['new_data'] = $newData;
            }

            $approvalRequest = ApprovalRequest::create($approvalData);

            return [
                'success' => true,
                'approval_required' => true,
                'approval_request' => $approvalRequest,
                'message' => 'Approval request created. Waiting for manager approval.'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create approval request', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create approval request: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate transaction data
     */
    private function validateTransactionData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['transaction_type'])) {
            $errors[] = 'Transaction type is required';
        }

        if (empty($data['fuel_amount']) || $data['fuel_amount'] <= 0) {
            $errors[] = 'Fuel amount must be greater than 0';
        }

        // Validate based on transaction type
        switch ($data['transaction_type'] ?? '') {
            case FuelTransaction::TYPE_STORAGE_TO_UNIT:
                if (empty($data['source_storage_id']) || empty($data['unit_id'])) {
                    $errors[] = 'Source storage and unit are required';
                }
                break;

            case FuelTransaction::TYPE_STORAGE_TO_TRUCK:
                if (empty($data['source_storage_id']) || empty($data['destination_truck_id'])) {
                    $errors[] = 'Source storage and destination truck are required';
                }
                break;

            case FuelTransaction::TYPE_TRUCK_TO_UNIT:
                if (empty($data['source_truck_id']) || empty($data['unit_id'])) {
                    $errors[] = 'Source truck and unit are required';
                }
                break;

            case FuelTransaction::TYPE_VENDOR_TO_STORAGE:
                if (empty($data['destination_storage_id'])) {
                    $errors[] = 'Destination storage is required';
                }
                break;
        }

        // Validate capacity availability
        if (!empty($data['source_storage_id'])) {
            $storage = FuelStorage::find($data['source_storage_id']);
            if ($storage && $storage->current_capacity < $data['fuel_amount']) {
                $errors[] = 'Insufficient fuel in source storage';
            }
        }

        if (!empty($data['source_truck_id'])) {
            $truck = FuelTruck::find($data['source_truck_id']);
            if ($truck && $truck->current_capacity < $data['fuel_amount']) {
                $errors[] = 'Insufficient fuel in source truck';
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Validation passed' : 'Validation failed'
        ];
    }

    /**
     * Prepare transaction data untuk create
     */
    private function prepareTransactionData(array $data, User $user): array
    {
        return [
            'transaction_type' => $data['transaction_type'],
            'source_storage_id' => $data['source_storage_id'] ?? null,
            'source_truck_id' => $data['source_truck_id'] ?? null,
            'destination_storage_id' => $data['destination_storage_id'] ?? null,
            'destination_truck_id' => $data['destination_truck_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'fuel_amount' => $data['fuel_amount'],
            'unit_km' => $data['unit_km'] ?? null,
            'unit_hm' => $data['unit_hm'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
            'is_approved' => $user->hasRole(['manager', 'superadmin']), // Auto approve untuk manager/superadmin
        ];
    }

    /**
     * Calculate fuel consumption
     */
    public function calculateConsumption(FuelTransaction $transaction): ?array
    {
        if (!$transaction->unit) {
            return null;
        }

        $previousTransaction = FuelTransaction::where('unit_id', $transaction->unit_id)
                                             ->where('id', '<', $transaction->id)
                                             ->orderBy('id', 'desc')
                                             ->first();

        if (!$previousTransaction) {
            return null;
        }

        $consumption = [];

        // Calculate per KM
        if ($transaction->unit_km && $previousTransaction->unit_km) {
            $kmDiff = $transaction->unit_km - $previousTransaction->unit_km;
            if ($kmDiff > 0) {
                $consumption['per_km'] = round($transaction->fuel_amount / $kmDiff, 2);
                $consumption['km_traveled'] = $kmDiff;
            }
        }

        // Calculate per HM
        if ($transaction->unit_hm && $previousTransaction->unit_hm) {
            $hmDiff = $transaction->unit_hm - $previousTransaction->unit_hm;
            if ($hmDiff > 0) {
                $consumption['per_hm'] = round($transaction->fuel_amount / $hmDiff, 2);
                $consumption['hm_operated'] = $hmDiff;
            }
        }

        return !empty($consumption) ? $consumption : null;
    }

    /**
     * Generate fuel consumption report by date range
     */
    public function generateConsumptionReport(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        try {
            $query = FuelTransaction::with(['unit', 'sourceStorage', 'sourceTruck'])
                                   ->whereBetween('transaction_date', [$startDate, $endDate])
                                   ->approved();

            // Apply filters
            if (!empty($filters['unit_id'])) {
                $query->where('unit_id', $filters['unit_id']);
            }

            if (!empty($filters['transaction_type'])) {
                $query->where('transaction_type', $filters['transaction_type']);
            }

            if (!empty($filters['unit_type'])) {
                $query->whereHas('unit', function($q) use ($filters) {
                    $q->where('type', $filters['unit_type']);
                });
            }

            $transactions = $query->orderBy('transaction_date', 'asc')->get();

            // Group dan analyze data
            $summary = $this->analyzeTransactions($transactions);

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'summary' => $summary,
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'days' => $startDate->diffInDays($endDate) + 1
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate consumption report', [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Analyze transactions untuk summary
     */
    private function analyzeTransactions($transactions): array
    {
        $summary = [
            'total_transactions' => $transactions->count(),
            'total_fuel_distributed' => 0,
            'by_transaction_type' => [],
            'by_unit_type' => [],
            'by_unit' => [],
            'daily_distribution' => [],
            'fuel_efficiency' => []
        ];

        $dailyData = [];
        $unitData = [];

        foreach ($transactions as $transaction) {
            $amount = $transaction->fuel_amount;
            $date = $transaction->transaction_date->format('Y-m-d');
            
            // Total fuel
            $summary['total_fuel_distributed'] += $amount;

            // By transaction type
            $type = $transaction->transaction_type;
            if (!isset($summary['by_transaction_type'][$type])) {
                $summary['by_transaction_type'][$type] = [
                    'count' => 0,
                    'total_amount' => 0
                ];
            }
            $summary['by_transaction_type'][$type]['count']++;
            $summary['by_transaction_type'][$type]['total_amount'] += $amount;

            // By unit type (jika ada unit)
            if ($transaction->unit) {
                $unitType = $transaction->unit->type;
                if (!isset($summary['by_unit_type'][$unitType])) {
                    $summary['by_unit_type'][$unitType] = [
                        'count' => 0,
                        'total_amount' => 0,
                        'units' => []
                    ];
                }
                $summary['by_unit_type'][$unitType]['count']++;
                $summary['by_unit_type'][$unitType]['total_amount'] += $amount;
                $summary['by_unit_type'][$unitType]['units'][] = $transaction->unit->code;

                // By specific unit
                $unitCode = $transaction->unit->code;
                if (!isset($unitData[$unitCode])) {
                    $unitData[$unitCode] = [
                        'unit_name' => $transaction->unit->name,
                        'unit_type' => $transaction->unit->type,
                        'total_fuel' => 0,
                        'transaction_count' => 0,
                        'consumption_data' => []
                    ];
                }
                $unitData[$unitCode]['total_fuel'] += $amount;
                $unitData[$unitCode]['transaction_count']++;

                // Calculate consumption jika ada data
                $consumption = $this->calculateConsumption($transaction);
                if ($consumption) {
                    $unitData[$unitCode]['consumption_data'][] = $consumption;
                }
            }

            // Daily distribution
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = 0;
            }
            $dailyData[$date] += $amount;
        }

        // Sort dan format data
        $summary['by_unit'] = collect($unitData)->sortBy('unit_name')->toArray();
        $summary['daily_distribution'] = collect($dailyData)->sortKeys()->toArray();

        // Calculate average consumption per unit type
        foreach ($summary['by_unit_type'] as $type => &$data) {
            $data['units'] = array_unique($data['units']);
            $data['unit_count'] = count($data['units']);
            $data['average_per_unit'] = $data['unit_count'] > 0 ? 
                round($data['total_amount'] / $data['unit_count'], 2) : 0;
        }

        // Calculate fuel efficiency metrics
        $summary['fuel_efficiency'] = $this->calculateEfficiencyMetrics($unitData);

        return $summary;
    }

    /**
     * Calculate fuel efficiency metrics
     */
    private function calculateEfficiencyMetrics(array $unitData): array
    {
        $efficiency = [
            'by_unit_type' => [],
            'top_efficient_units' => [],
            'least_efficient_units' => []
        ];

        $unitEfficiency = [];

        foreach ($unitData as $unitCode => $data) {
            if (empty($data['consumption_data'])) continue;

            $consumptions = $data['consumption_data'];
            $avgPerKm = 0;
            $avgPerHm = 0;
            $kmCount = 0;
            $hmCount = 0;

            foreach ($consumptions as $consumption) {
                if (isset($consumption['per_km'])) {
                    $avgPerKm += $consumption['per_km'];
                    $kmCount++;
                }
                if (isset($consumption['per_hm'])) {
                    $avgPerHm += $consumption['per_hm'];
                    $hmCount++;
                }
            }

            $unitEfficiencyData = [
                'unit_code' => $unitCode,
                'unit_name' => $data['unit_name'],
                'unit_type' => $data['unit_type']
            ];

            if ($kmCount > 0) {
                $unitEfficiencyData['avg_consumption_per_km'] = round($avgPerKm / $kmCount, 2);
            }

            if ($hmCount > 0) {
                $unitEfficiencyData['avg_consumption_per_hm'] = round($avgPerHm / $hmCount, 2);
            }

            if ($kmCount > 0 || $hmCount > 0) {
                $unitEfficiency[] = $unitEfficiencyData;

                // Group by unit type
                $type = $data['unit_type'];
                if (!isset($efficiency['by_unit_type'][$type])) {
                    $efficiency['by_unit_type'][$type] = [
                        'units' => [],
                        'avg_km_consumption' => 0,
                        'avg_hm_consumption' => 0,
                        'km_count' => 0,
                        'hm_count' => 0
                    ];
                }

                if (isset($unitEfficiencyData['avg_consumption_per_km'])) {
                    $efficiency['by_unit_type'][$type]['avg_km_consumption'] += $unitEfficiencyData['avg_consumption_per_km'];
                    $efficiency['by_unit_type'][$type]['km_count']++;
                }

                if (isset($unitEfficiencyData['avg_consumption_per_hm'])) {
                    $efficiency['by_unit_type'][$type]['avg_hm_consumption'] += $unitEfficiencyData['avg_consumption_per_hm'];
                    $efficiency['by_unit_type'][$type]['hm_count']++;
                }

                $efficiency['by_unit_type'][$type]['units'][] = $unitEfficiencyData;
            }
        }

        // Calculate averages by unit type
        foreach ($efficiency['by_unit_type'] as $type => &$data) {
            if ($data['km_count'] > 0) {
                $data['avg_km_consumption'] = round($data['avg_km_consumption'] / $data['km_count'], 2);
            }
            if ($data['hm_count'] > 0) {
                $data['avg_hm_consumption'] = round($data['avg_hm_consumption'] / $data['hm_count'], 2);
            }
        }

        // Sort units by efficiency (lower consumption = more efficient)
        if (!empty($unitEfficiency)) {
            // Sort by KM consumption (most efficient first)
            $kmEfficient = collect($unitEfficiency)
                ->filter(fn($unit) => isset($unit['avg_consumption_per_km']))
                ->sortBy('avg_consumption_per_km')
                ->values()
                ->toArray();

            // Sort by HM consumption (most efficient first)
            $hmEfficient = collect($unitEfficiency)
                ->filter(fn($unit) => isset($unit['avg_consumption_per_hm']))
                ->sortBy('avg_consumption_per_hm')
                ->values()
                ->toArray();

            $efficiency['top_efficient_units'] = [
                'by_km' => array_slice($kmEfficient, 0, 5),
                'by_hm' => array_slice($hmEfficient, 0, 5)
            ];

            $efficiency['least_efficient_units'] = [
                'by_km' => array_slice(array_reverse($kmEfficient), 0, 5),
                'by_hm' => array_slice(array_reverse($hmEfficient), 0, 5)
            ];
        }

        return $efficiency;
    }

    /**
     * Get dashboard summary data
     */
    public function getDashboardSummary(): array
    {
        try {
            $today = now();
            $thisMonth = $today->copy()->startOfMonth();
            $lastMonth = $today->copy()->subMonth()->startOfMonth();

            $summary = [
                'storage_status' => $this->getStorageStatus(),
                'truck_status' => $this->getTruckStatus(),
                'recent_transactions' => $this->getRecentTransactions(),
                'monthly_distribution' => $this->getMonthlyDistribution($thisMonth),
                'alerts' => $this->getActiveAlerts(),
                'pending_approvals' => $this->getPendingApprovals()
            ];

            return [
                'success' => true,
                'data' => $summary
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get dashboard summary', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get dashboard data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get storage status untuk dashboard
     */
    private function getStorageStatus(): array
    {
        $storages = FuelStorage::active()->get();
        
        return [
            'total_storages' => $storages->count(),
            'total_capacity' => $storages->sum('max_capacity'),
            'current_fuel' => $storages->sum('current_capacity'),
            'below_threshold' => $storages->filter(fn($s) => $s->isBelowThreshold())->count(),
            'storages' => $storages->map(function($storage) {
                return [
                    'id' => $storage->id,
                    'name' => $storage->name,
                    'code' => $storage->code,
                    'current_capacity' => $storage->current_capacity,
                    'max_capacity' => $storage->max_capacity,
                    'percentage' => $storage->capacity_percentage,
                    'is_below_threshold' => $storage->isBelowThreshold()
                ];
            })
        ];
    }

    /**
     * Get truck status untuk dashboard
     */
    private function getTruckStatus(): array
    {
        $trucks = FuelTruck::active()->get();
        
        return [
            'total_trucks' => $trucks->count(),
            'available' => $trucks->where('status', FuelTruck::STATUS_AVAILABLE)->count(),
            'in_use' => $trucks->where('status', FuelTruck::STATUS_IN_USE)->count(),
            'maintenance' => $trucks->where('status', FuelTruck::STATUS_MAINTENANCE)->count(),
            'total_capacity' => $trucks->sum('max_capacity'),
            'current_fuel' => $trucks->sum('current_capacity'),
            'trucks' => $trucks->map(function($truck) {
                return [
                    'id' => $truck->id,
                    'name' => $truck->name,
                    'code' => $truck->code,
                    'status' => $truck->status,
                    'current_capacity' => $truck->current_capacity,
                    'max_capacity' => $truck->max_capacity,
                    'percentage' => $truck->capacity_percentage
                ];
            })
        ];
    }

    /**
     * Get recent transactions
     */
    private function getRecentTransactions(int $limit = 10): array
    {
        return FuelTransaction::with(['unit', 'sourceStorage', 'sourceTruck', 'createdBy'])
                             ->orderBy('created_at', 'desc')
                             ->limit($limit)
                             ->get()
                             ->map(function($transaction) {
                                 return [
                                     'id' => $transaction->id,
                                     'transaction_code' => $transaction->transaction_code,
                                     'type' => $transaction->transaction_type,
                                     'source' => $transaction->source_name,
                                     'destination' => $transaction->destination_name,
                                     'amount' => $transaction->fuel_amount,
                                     'date' => $transaction->transaction_date,
                                     'created_by' => $transaction->createdBy->name,
                                     'is_approved' => $transaction->is_approved
                                 ];
                             })->toArray();
    }

    /**
     * Get monthly distribution data
     */
    private function getMonthlyDistribution(Carbon $month): array
    {
        $transactions = FuelTransaction::whereYear('transaction_date', $month->year)
                                      ->whereMonth('transaction_date', $month->month)
                                      ->approved()
                                      ->get();

        return [
            'total_amount' => $transactions->sum('fuel_amount'),
            'transaction_count' => $transactions->count(),
            'by_type' => $transactions->groupBy('transaction_type')
                                    ->map(function($group, $type) {
                                        return [
                                            'type' => $type,
                                            'count' => $group->count(),
                                            'amount' => $group->sum('fuel_amount')
                                        ];
                                    })->values()->toArray()
        ];
    }

    /**
     * Get active alerts
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];

        // Storage alerts
        $lowStorages = FuelStorage::belowThreshold()->active()->get();
        foreach ($lowStorages as $storage) {
            $alerts[] = [
                'type' => 'low_storage',
                'severity' => $storage->capacity_percentage <= 5 ? 'critical' : 'warning',
                'message' => "Storage {$storage->name} is below threshold ({$storage->capacity_percentage}%)",
                'entity_id' => $storage->id,
                'entity_type' => 'storage'
            ];
        }

        // Maintenance alerts
        $maintenanceTrucks = FuelTruck::where('status', FuelTruck::STATUS_MAINTENANCE)->count();
        if ($maintenanceTrucks > 0) {
            $alerts[] = [
                'type' => 'maintenance',
                'severity' => 'info',
                'message' => "{$maintenanceTrucks} truck(s) are under maintenance",
                'entity_type' => 'truck'
            ];
        }

        return $alerts;
    }

    /**
     * Get pending approvals count
     */
    private function getPendingApprovals(): int
    {
        return ApprovalRequest::pending()->count();
    }
}