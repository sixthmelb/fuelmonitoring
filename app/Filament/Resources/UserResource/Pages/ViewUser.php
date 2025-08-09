<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

/**
 * Command untuk membuat page ini:
 * php artisan make:filament-page ViewUser --resource=UserResource --type=ViewRecord
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('toggle_status')
                ->label(fn (): string => $this->record->is_active ? 'Deactivate User' : 'Activate User')
                ->icon(fn (): string => $this->record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn (): string => $this->record->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 
                    $this->record->is_active 
                        ? 'This will prevent the user from logging in to the system.'
                        : 'This will allow the user to log in to the system again.'
                )
                ->action(function (): void {
                    $this->record->update(['is_active' => !$this->record->is_active]);
                    $this->refreshFormData(['record']);
                })
                ->successNotificationTitle(fn (): string => 
                    'User ' . ($this->record->is_active ? 'activated' : 'deactivated') . ' successfully'
                ),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Profile')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight('bold')
                                    ->size('lg'),
                                    
                                Infolists\Components\TextEntry::make('email')
                                    ->icon('heroicon-m-envelope')
                                    ->copyable(),
                                    
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('employee_id')
                                    ->label('Employee ID')
                                    ->badge()
                                    ->color('gray'),
                                    
                                Infolists\Components\TextEntry::make('department')
                                    ->placeholder('Not assigned'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('Not provided'),
                    ])
                    ->icon('heroicon-o-user'),
                    
                Infolists\Components\Section::make('Roles & Permissions')
                    ->schema([
                        Infolists\Components\TextEntry::make('roles.name')
                            ->label('Assigned Roles')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'superadmin' => 'danger',
                                'manager' => 'warning', 
                                'staff' => 'success',
                                default => 'gray'
                            })
                            ->separator(','),
                            
                        Infolists\Components\ViewEntry::make('permissions_summary')
                            ->label('Key Permissions')
                            ->view('filament.infolists.user-permissions')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-key')
                    ->collapsible(),
                    
                Infolists\Components\Section::make('Account Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('email_verified_at')
                                    ->label('Email Verified')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->placeholder('Not verified')
                                    ->icon('heroicon-o-check-badge'),
                                    
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Account Created')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->icon('heroicon-o-calendar-days'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->icon('heroicon-o-information-circle')
                    ->collapsible(),
                    
                Infolists\Components\Section::make('Activity Summary')
                    ->schema([
                        Infolists\Components\ViewEntry::make('activity_stats')
                            ->label('')
                            ->view('filament.infolists.user-activity')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-chart-bar')
                    ->collapsible(),
            ]);
    }
}