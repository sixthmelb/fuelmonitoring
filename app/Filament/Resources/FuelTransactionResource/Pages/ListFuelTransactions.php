<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use App\Models\FuelTransaction;
use App\Models\ApprovalRequest;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * File: app/Filament/Resources/FuelTransactionResource/Pages/ListFuelTransactions.php
 * * Command untuk update file ini:
 * php artisan make:filament-page ListFuelTransactions --resource=FuelTransactionResource --type=ListRecords
 * * PERBAIKAN:
 * 1. Tambah tabs untuk filtering berdasarkan role
 * 2. Different views untuk staff vs manager
 */
class ListFuelTransactions extends ListRecords
{
    protected static string $resource = FuelTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Transaction'),
                
            // Shortcut ke approval requests untuk manager
            Actions\Action::make('view_approvals')
                ->label('Pending Approvals')
                ->icon('heroicon-o-document-check')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()->hasAnyRole(['manager', 'superadmin']))
                ->badge(fn (): ?string => 
                    ($count = ApprovalRequest::pending()->count()) > 0 ? (string) $count : null
                )
                ->badgeColor('warning')
                //->url(ApprovalRequestResource::getUrl('index')),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        // Hitung badge count sekali untuk efisiensi
        $allCount = FuelTransaction::count();
        $approvedCount = FuelTransaction::where('is_approved', true)->count();
        $pendingCount = FuelTransaction::where('is_approved', false)->count();

        $baseTabs = [
            'all' => Tab::make('All Transactions')
                ->icon('heroicon-o-document-text')
                ->badge($allCount)
        ];

        // Manager/Superadmin tabs
        if ($user->hasAnyRole(['manager', 'superadmin'])) {
            $pendingEditRequestsCount = FuelTransaction::whereHas('approvalRequests', function ($q) {
                $q->where('status', ApprovalRequest::STATUS_PENDING);
            })->count();

            return array_merge($baseTabs, [
                'pending_approval' => Tab::make('Pending Approval')
                    ->icon('heroicon-o-clock')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', false))
                    ->badge($pendingCount)
                    ->badgeColor('warning'),
                
                'approved' => Tab::make('Approved')
                    ->icon('heroicon-o-check-circle')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', true))
                    ->badge($approvedCount)
                    ->badgeColor('success'),
                
                'with_edit_requests' => Tab::make('Has Pending Edit Requests')
                    ->icon('heroicon-o-pencil-square')
                    ->modifyQueryUsing(fn (Builder $query) => 
                        $query->whereHas('approvalRequests', fn ($q) => 
                            $q->where('status', ApprovalRequest::STATUS_PENDING)
                        )
                    )
                    ->badge($pendingEditRequestsCount)
                    ->badgeColor('danger'),
                
                'today' => Tab::make('Today')
                    ->icon('heroicon-o-calendar-days')
                    ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today()))
                    ->badge(FuelTransaction::whereDate('created_at', today())->count())
                    ->badgeColor('info'),
                
                'this_week' => Tab::make('This Week')
                    ->icon('heroicon-o-calendar')
                    ->modifyQueryUsing(fn (Builder $query) => 
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    )
                    ->badge(FuelTransaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count())
                    ->badgeColor('primary'),
            ]);
        }
        
        // Staff tabs (default)
        $myTransactionsCount = FuelTransaction::where('created_by', auth()->id())->count();
        $myRequestsCount = FuelTransaction::whereHas('approvalRequests', fn ($q) => 
            $q->where('requested_by', auth()->id())
        )->count();

        return array_merge($baseTabs, [
            'my_transactions' => Tab::make('My Transactions')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_by', auth()->id()))
                ->badge($myTransactionsCount)
                ->badgeColor('primary'),
                
            'approved' => Tab::make('Approved')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', true))
                ->badge($approvedCount)
                ->badgeColor('success'),
                
            'pending_approval' => Tab::make('Pending Approval')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', false))
                ->badge($pendingCount)
                ->badgeColor('warning'),
                
            'my_requests' => Tab::make('My Edit Requests')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('approvalRequests', fn ($q) => 
                        $q->where('requested_by', auth()->id())
                    )
                )
                ->badge($myRequestsCount)
                ->badgeColor('info'),
        ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
        
        // Staff hanya bisa lihat transaksi mereka sendiri atau yang sudah disetujui (approved)
        if (auth()->user()->hasRole('staff')) {
            return $query->where(function ($q) {
                $q->where('is_approved', true)
                  ->orWhere('created_by', auth()->id());
            });
        }
        
        // Manager/superadmin bisa lihat semua
        return $query;
    }
}