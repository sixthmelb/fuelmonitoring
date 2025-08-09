<?php

namespace App\Filament\Resources\ApprovalRequestResource\Pages;

use App\Filament\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Command untuk membuat page ini:
 * php artisan make:filament-page ListApprovalRequests --resource=ApprovalRequestResource --type=ListRecords
 * 
 * Page untuk list approval requests dengan tabs filter
 */
class ListApprovalRequests extends ListRecords
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Biasanya approval request tidak dibuat manual, 
            // tetapi otomatis dari sistem saat ada edit/delete request
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->resetTable()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Requests')
                ->icon('heroicon-o-document-text')
                ->badge(ApprovalRequest::count()),
                
            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApprovalRequest::STATUS_PENDING))
                ->badge(ApprovalRequest::pending()->count())
                ->badgeColor('warning'),
                
            'approved' => Tab::make('Approved')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApprovalRequest::STATUS_APPROVED))
                ->badge(ApprovalRequest::approved()->count())
                ->badgeColor('success'),
                
            'rejected' => Tab::make('Rejected')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ApprovalRequest::STATUS_REJECTED))
                ->badge(ApprovalRequest::rejected()->count())
                ->badgeColor('danger'),
                
            'edit_requests' => Tab::make('Edit Requests')
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('request_type', ApprovalRequest::TYPE_EDIT))
                ->badge(ApprovalRequest::byType(ApprovalRequest::TYPE_EDIT)->count())
                ->badgeColor('info'),
                
            'delete_requests' => Tab::make('Delete Requests')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('request_type', ApprovalRequest::TYPE_DELETE))
                ->badge(ApprovalRequest::byType(ApprovalRequest::TYPE_DELETE)->count())
                ->badgeColor('gray'),
                
            'my_requests' => Tab::make('My Requests')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('requested_by', auth()->id()))
                ->badge(ApprovalRequest::byRequester(auth()->id())->count())
                ->badgeColor('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Bisa ditambahkan widget summary approval jika diperlukan
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Bisa ditambahkan widget statistik approval jika diperlukan
        ];
    }
}