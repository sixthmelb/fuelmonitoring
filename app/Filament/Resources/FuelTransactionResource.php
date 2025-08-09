<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelTransactionResource\Pages;
use App\Models\FuelTransaction;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Filament Resource untuk Fuel Transaction Management
 * 
 * Features:
 * - Dynamic form berdasarkan transaction type
 * - Real-time capacity validation
 * - Fuel consumption calculation
 * - Approval workflow integration
 * - Advanced filtering dan reporting
 */
class FuelTransactionResource extends Resource
{
    protected static ?string $model = FuelTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';
    
    protected static ?string $navigationLabel = 'Fuel Transactions';
    
    protected static ?string $modelLabel = 'Fuel Transaction';
    
    protected static ?string $pluralModelLabel = 'Fuel Transactions';
    
    protected static ?string $navigationGroup = 'Fuel Management';
    
    protected static ?int $navigationSort = 4;

public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Information')
                    ->description('Basic transaction details and type selection')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('transaction_code')
                                    ->label('Transaction Code')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated')
                                    ->helperText('Will be generated automatically upon save'),
                                    
                                Forms\Components\Select::make('transaction_type')
                                    ->label('Transaction Type')
                                    ->required()
                                    ->options(FuelTransaction::getTransactionTypes())
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        // Clear related fields when transaction type changes
                                        $set('source_storage_id', null);
                                        $set('source_truck_id', null);
                                        $set('destination_storage_id', null);
                                        $set('destination_truck_id', null);
                                        $set('unit_id', null);
                                        $set('fuel_amount', null);
                                    }),
                                    
                                Forms\Components\DateTimePicker::make('transaction_date')
                                    ->label('Transaction Date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Source & Destination')
                    ->description('Select source and destination based on transaction type')
                    ->schema([
                        // Source Storage (for storage-based transactions)
                        Forms\Components\Select::make('source_storage_id')
                            ->label('Source Storage')
                            ->options(fn () => FuelStorage::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => 
                                in_array($get('transaction_type'), [
                                    FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                    FuelTransaction::TYPE_STORAGE_TO_TRUCK
                                ])
                            )
                            ->required(fn (Get $get): bool => 
                                in_array($get('transaction_type'), [
                                    FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                    FuelTransaction::TYPE_STORAGE_TO_TRUCK
                                ])
                            )
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $storage = FuelStorage::find($state);
                                    $set('max_available_fuel', $storage?->current_capacity ?? 0);
                                }
                            }),
                            
                        // Source Truck (for truck-based transactions)
                        Forms\Components\Select::make('source_truck_id')
                            ->label('Source Truck')
                            ->options(fn () => FuelTruck::available()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_TRUCK_TO_UNIT
                            )
                            ->required(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_TRUCK_TO_UNIT
                            )
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                if ($state) {
                                    $truck = FuelTruck::find($state);
                                    $set('max_available_fuel', $truck?->current_capacity ?? 0);
                                }
                            }),
                            
                        // Destination Storage (for vendor supply)
                        Forms\Components\Select::make('destination_storage_id')
                            ->label('Destination Storage')
                            ->options(fn () => FuelStorage::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_VENDOR_TO_STORAGE
                            )
                            ->required(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_VENDOR_TO_STORAGE
                            ),
                            
                        // Destination Truck (for storage to truck)
                        Forms\Components\Select::make('destination_truck_id')
                            ->label('Destination Truck')
                            ->options(fn () => FuelTruck::available()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_STORAGE_TO_TRUCK
                            )
                            ->required(fn (Get $get): bool => 
                                $get('transaction_type') === FuelTransaction::TYPE_STORAGE_TO_TRUCK
                            ),
                            
                        // Unit (for transactions to units)
                        Forms\Components\Select::make('unit_id')
                            ->label('Unit')
                            ->options(fn () => Unit::active()->get()->pluck('name_with_code', 'id'))
                            ->getOptionLabelFromRecordUsing(fn (Unit $record): string => "{$record->name} ({$record->code})")
                            ->searchable(['name', 'code'])
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => 
                                in_array($get('transaction_type'), [
                                    FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                    FuelTransaction::TYPE_TRUCK_TO_UNIT
                                ])
                            )
                            ->required(fn (Get $get): bool => 
                                in_array($get('transaction_type'), [
                                    FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                    FuelTransaction::TYPE_TRUCK_TO_UNIT
                                ])
                            )
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $unit = Unit::find($state);
                                    $set('current_unit_km', $unit?->current_km ?? 0);
                                    $set('current_unit_hm', $unit?->current_hm ?? 0);
                                }
                            }),
                    ]),
                    
                Forms\Components\Section::make('Fuel Details')
                    ->description('Specify fuel amount and unit metrics')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('fuel_amount')
                                    ->label('Fuel Amount (Liters)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get): float => (float) $get('max_available_fuel') ?: 999999)
                                    ->live(onBlur: true)
                                    ->suffix('L')
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $maxAvailable = (float) $get('max_available_fuel');
                                                if ($maxAvailable > 0 && $value > $maxAvailable) {
                                                    $fail("Only {$maxAvailable}L available in selected source");
                                                }
                                            };
                                        },
                                    ]),
                                    
                                Forms\Components\TextInput::make('unit_km')
                                    ->label('Unit KM')
                                    ->numeric()
                                    ->minValue(fn (Get $get): float => (float) $get('current_unit_km') ?: 0)
                                    ->visible(fn (Get $get): bool => 
                                        in_array($get('transaction_type'), [
                                            FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                            FuelTransaction::TYPE_TRUCK_TO_UNIT
                                        ]) && $get('unit_id')
                                    )
                                    ->helperText(fn (Get $get): string => 
                                        $get('current_unit_km') ? 
                                        'Current KM: ' . number_format($get('current_unit_km'), 0) : ''
                                    )
                                    ->suffix('KM'),
                                    
                                Forms\Components\TextInput::make('unit_hm')
                                    ->label('Unit HM (Hour Meter)')
                                    ->numeric()
                                    ->minValue(fn (Get $get): float => (float) $get('current_unit_hm') ?: 0)
                                    ->visible(fn (Get $get): bool => 
                                        in_array($get('transaction_type'), [
                                            FuelTransaction::TYPE_STORAGE_TO_UNIT,
                                            FuelTransaction::TYPE_TRUCK_TO_UNIT
                                        ]) && $get('unit_id')
                                    )
                                    ->helperText(fn (Get $get): string => 
                                        $get('current_unit_hm') ? 
                                        'Current HM: ' . number_format($get('current_unit_hm'), 1) : ''
                                    )
                                    ->suffix('HM'),
                            ]),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Optional notes about this transaction')
                            ->columnSpanFull(),
                            
                        // Hidden fields for calculations
                        Forms\Components\Hidden::make('max_available_fuel'),
                        Forms\Components\Hidden::make('current_unit_km'),
                        Forms\Components\Hidden::make('current_unit_hm'),
                        
                        // Hidden field untuk created_by (akan diisi otomatis)
                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),
                    
                Forms\Components\Section::make('Estimated Consumption')
                    ->description('Fuel consumption calculation based on previous data')
                    ->schema([
                        Forms\Components\Placeholder::make('consumption_estimate')
                            ->label('')
                            ->content(function (Get $get): string {
                                $unitId = $get('unit_id');
                                $newKm = (float) $get('unit_km');
                                $newHm = (float) $get('unit_hm');
                                $fuelAmount = (float) $get('fuel_amount');
                                
                                if (!$unitId || !$fuelAmount) {
                                    return 'Select unit and enter fuel amount to see consumption estimate';
                                }
                                
                                $unit = Unit::find($unitId);
                                if (!$unit) return 'Unit not found';
                                
                                $estimate = $unit->estimateFuelConsumption($newKm, $newHm);
                                $efficiency = '';
                                
                                if ($newKm && $unit->current_km) {
                                    $kmDiff = $newKm - $unit->current_km;
                                    if ($kmDiff > 0) {
                                        $consPerKm = $fuelAmount / $kmDiff;
                                        $efficiency .= "Consumption: " . number_format($consPerKm, 2) . " L/KM\n";
                                    }
                                }
                                
                                if ($newHm && $unit->current_hm) {
                                    $hmDiff = $newHm - $unit->current_hm;
                                    if ($hmDiff > 0) {
                                        $consPerHm = $fuelAmount / $hmDiff;
                                        $efficiency .= "Consumption: " . number_format($consPerHm, 2) . " L/HM\n";
                                    }
                                }
                                
                                if ($estimate) {
                                    $efficiency .= "Estimated need: " . number_format($estimate, 0) . " L";
                                }
                                
                                return $efficiency ?: 'No previous data available for consumption calculation';
                            }),
                    ])
                    ->visible(fn (Get $get): bool => 
                        in_array($get('transaction_type'), [
                            FuelTransaction::TYPE_STORAGE_TO_UNIT,
                            FuelTransaction::TYPE_TRUCK_TO_UNIT
                        ])
                    )
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                    
                Tables\Columns\BadgeColumn::make('transaction_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => 
                        FuelTransaction::getTransactionTypes()[$state] ?? $state
                    )
                    ->color(fn (string $state): string => match($state) {
                        FuelTransaction::TYPE_VENDOR_TO_STORAGE => 'success',
                        FuelTransaction::TYPE_STORAGE_TO_TRUCK => 'info',
                        FuelTransaction::TYPE_STORAGE_TO_UNIT => 'warning',
                        FuelTransaction::TYPE_TRUCK_TO_UNIT => 'primary',
                        default => 'gray'
                    })
                    ->icon(fn (string $state): string => match($state) {
                        FuelTransaction::TYPE_VENDOR_TO_STORAGE => 'heroicon-o-arrow-down-circle',
                        FuelTransaction::TYPE_STORAGE_TO_TRUCK => 'heroicon-o-truck',
                        FuelTransaction::TYPE_STORAGE_TO_UNIT => 'heroicon-o-cog-6-tooth',
                        FuelTransaction::TYPE_TRUCK_TO_UNIT => 'heroicon-o-arrow-right-circle',
                        default => 'heroicon-o-question-mark-circle'
                    }),
                    
                Tables\Columns\TextColumn::make('source_name')
                    ->label('From')
                    ->getStateUsing(function (FuelTransaction $record): string {
                        return $record->source_name;
                    })
                    ->searchable()
                    ->sortable(false),
                    
                Tables\Columns\TextColumn::make('destination_name')
                    ->label('To')
                    ->getStateUsing(function (FuelTransaction $record): string {
                        return $record->destination_name;
                    })
                    ->searchable()
                    ->sortable(false),
                    
                Tables\Columns\TextColumn::make('fuel_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                    ->sortable()
                    ->alignment('right')
                    ->weight('bold'),
                    
                Tables\Columns\ViewColumn::make('consumption_info')
                    ->label('Consumption')
                    ->view('filament.columns.consumption-info')
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                    
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                
                Tables\Filters\SelectFilter::make('transaction_type')
                    ->options(FuelTransaction::getTransactionTypes())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('is_approved')
                    ->label('Approval Status')
                    ->options([
                        1 => 'Approved',
                        0 => 'Pending Approval',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value'])) {
                            return $query->where('is_approved', (bool) $data['value']);
                        }
                        return $query;
                    }),
                    
                Tables\Filters\SelectFilter::make('storage')
                    ->relationship('sourceStorage', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\Filter::make('fuel_amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_amount')
                                    ->label('Min Amount (L)')
                                    ->numeric(),
                                Forms\Components\TextInput::make('max_amount')
                                    ->label('Max Amount (L)')
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('fuel_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('fuel_amount', '<=', $amount),
                            );
                    }),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (FuelTransaction $record): bool => 
                        auth()->user()->hasRole(['superadmin', 'manager']) || 
                        $record->canBeEdited()
                    ),
                    
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FuelTransaction $record): bool => 
                        !$record->is_approved && 
                        auth()->user()->hasRole(['superadmin', 'manager'])
                    )
                    ->requiresConfirmation()
                    ->action(function (FuelTransaction $record): void {
                        $record->update([
                            'is_approved' => true,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Transaction approved successfully'),
                    
                Tables\Actions\Action::make('request_edit')
                    ->label('Request Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (FuelTransaction $record): bool => 
                        $record->requiresApprovalForEdit() && 
                        auth()->user()->hasRole('staff')
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Edit Request')
                            ->required()
                            ->placeholder('Please explain why this transaction needs to be edited'),
                    ])
                    ->action(function (FuelTransaction $record, array $data): void {
                        \App\Models\ApprovalRequest::create([
                            'fuel_transaction_id' => $record->id,
                            'request_type' => 'edit',
                            'requested_by' => auth()->id(),
                            'reason' => $data['reason'],
                            'original_data' => $record->toArray(),
                        ]);
                    })
                    ->successNotificationTitle('Edit request submitted for approval'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole(['superadmin', 'manager'])),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('superadmin')),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('superadmin')),
                        
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()->hasRole(['superadmin', 'manager']))
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each(function (FuelTransaction $record) {
                                if (!$record->is_approved) {
                                    $record->update([
                                        'is_approved' => true,
                                        'approved_by' => auth()->id(),
                                        'approved_at' => now(),
                                    ]);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFuelTransactions::route('/'),
            'create' => Pages\CreateFuelTransaction::route('/create'),
            //'view' => Pages\ViewFuelTransaction::route('/{record}'),
            'edit' => Pages\EditFuelTransaction::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['sourceStorage', 'sourceTruck', 'destinationStorage', 'destinationTruck', 'unit', 'createdBy']);
    }
    
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::pendingApproval()->count();
        return $pending > 0 ? (string) $pending : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $pending = static::getModel()::pendingApproval()->count();
        return $pending > 0 ? 'warning' : null;
    }
    
}