<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelTruckResource\Pages;
use App\Models\FuelTruck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;

/**
 * Filament Resource untuk Fuel Truck Management
 * 
 * Features:
 * - Real-time capacity dan status monitoring
 * - Driver management integration
 * - Maintenance scheduling
 * - Route tracking dan efficiency
 */
class FuelTruckResource extends Resource
{
    protected static ?string $model = FuelTruck::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    
    protected static ?string $navigationLabel = 'Fuel Trucks';
    
    protected static ?string $modelLabel = 'Fuel Truck';
    
    protected static ?string $pluralModelLabel = 'Fuel Trucks';
    
    protected static ?string $navigationGroup = 'Fuel Management';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Truck Information')
                    ->description('Basic truck identification and details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Truck Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Fuel Truck Alpha'),
                                    
                                Forms\Components\TextInput::make('code')
                                    ->label('Truck Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., FT-001')
                                    ->rules(['regex:/^[A-Z]{2}-\d{3}$/'])
                                    ->validationMessages([
                                        'regex' => 'Code must follow format: FT-001',
                                    ]),
                            ]),
                            
                        Forms\Components\TextInput::make('license_plate')
                            ->label('License Plate')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., B 1234 ABC')
                            ->prefixIcon('heroicon-o-identification'),
                    ]),
                    
                Forms\Components\Section::make('Driver Information')
                    ->description('Assign driver and contact details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('driver_name')
                                    ->label('Driver Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Full driver name'),
                                    
                                Forms\Components\TextInput::make('driver_phone')
                                    ->label('Driver Phone')
                                    ->tel()
                                    ->maxLength(20)
                                    ->placeholder('e.g., 081234567890')
                                    ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\./0-9]*$/'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Capacity & Status')
                    ->description('Fuel capacity and operational status')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('max_capacity')
                                    ->label('Maximum Capacity (Liters)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50000)
                                    ->step(100)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        // Ensure current_capacity doesn't exceed max
                                        $current = $get('current_capacity');
                                        if ($current && $current > $state) {
                                            $set('current_capacity', $state);
                                        }
                                    })
                                    ->suffix('L'),
                                    
                                Forms\Components\TextInput::make('current_capacity')
                                    ->label('Current Capacity (Liters)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $max = $get('max_capacity');
                                        if ($max && $state > $max) {
                                            $set('current_capacity', $max);
                                        }
                                    })
                                    ->suffix('L'),
                                    
                                Forms\Components\Select::make('status')
                                    ->label('Operational Status')
                                    ->required()
                                    ->options(FuelTruck::getStatuses())
                                    ->default(FuelTruck::STATUS_AVAILABLE)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        // Auto-suggest actions based on status
                                        if ($state === FuelTruck::STATUS_MAINTENANCE) {
                                            $set('last_maintenance', now()->format('Y-m-d'));
                                        }
                                    }),
                            ]),
                            
                        // Capacity Preview
                        Forms\Components\Placeholder::make('capacity_preview')
                            ->label('Capacity Preview')
                            ->content(function (Forms\Get $get): string {
                                $max = (float) $get('max_capacity');
                                $current = (float) $get('current_capacity');
                                
                                if (!$max) return 'Enter maximum capacity to see preview';
                                
                                $percentage = $max > 0 ? ($current / $max) * 100 : 0;
                                $available = $max - $current;
                                
                                $status = match(true) {
                                    $percentage >= 80 => '🟢 Full',
                                    $percentage >= 50 => '🟡 Half Full',
                                    $percentage >= 20 => '🟠 Low',
                                    $percentage > 0 => '🔴 Very Low',
                                    default => '⚫ Empty'
                                };
                                
                                return "
                                    <div class='space-y-2'>
                                        <div><strong>Status:</strong> {$status}</div>
                                        <div><strong>Usage:</strong> " . number_format($percentage, 1) . "%</div>
                                        <div><strong>Available Space:</strong> " . number_format($available, 0) . " L</div>
                                    </div>
                                ";
                            })
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Maintenance')
                    ->description('Maintenance schedule and history')
                    ->schema([
                        Forms\Components\DatePicker::make('last_maintenance')
                            ->label('Last Maintenance Date')
                            ->maxDate(now())
                            ->displayFormat('d/m/Y')
                            ->helperText('Track maintenance history for optimal performance'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive trucks will not appear in transaction options'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Truck Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('license_plate')
                    ->label('License Plate')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable()
                    ->limit(20),
                    
                Tables\Columns\ViewColumn::make('capacity_bar')
                    ->label('Fuel Level')
                    ->view('filament.columns.truck-capacity-bar'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => 
                        FuelTruck::getStatuses()[$state] ?? $state
                    )
                    ->color(fn (string $state): string => match($state) {
                        FuelTruck::STATUS_AVAILABLE => 'success',
                        FuelTruck::STATUS_IN_USE => 'primary',
                        FuelTruck::STATUS_MAINTENANCE => 'warning',
                        FuelTruck::STATUS_OUT_OF_SERVICE => 'danger',
                        default => 'gray'
                    })
                    ->icon(fn (string $state): string => match($state) {
                        FuelTruck::STATUS_AVAILABLE => 'heroicon-o-check-circle',
                        FuelTruck::STATUS_IN_USE => 'heroicon-o-play-circle',
                        FuelTruck::STATUS_MAINTENANCE => 'heroicon-o-wrench-screwdriver',
                        FuelTruck::STATUS_OUT_OF_SERVICE => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle'
                    }),
                    
                Tables\Columns\TextColumn::make('last_maintenance')
                    ->label('Last Service')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable()
                    ->color(function ($record): string {
                        if (!$record->last_maintenance) return 'gray';
                        $daysSince = $record->last_maintenance->diffInDays(now());
                        return match(true) {
                            $daysSince > 90 => 'danger',
                            $daysSince > 60 => 'warning',
                            default => 'success'
                        };
                    }),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options(FuelTruck::getStatuses())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('maintenance_status')
                    ->label('Maintenance Status')
                    ->options([
                        'recent' => 'Recently Serviced (< 30 days)',
                        'due_soon' => 'Service Due Soon (30-60 days)',
                        'overdue' => 'Service Overdue (> 60 days)',
                        'unknown' => 'No Service Record',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'recent',
                            fn (Builder $query): Builder => $query->where('last_maintenance', '>=', now()->subDays(30)),
                        )->when(
                            $data['value'] === 'due_soon',
                            fn (Builder $query): Builder => $query->whereBetween('last_maintenance', [now()->subDays(60), now()->subDays(30)]),
                        )->when(
                            $data['value'] === 'overdue',
                            fn (Builder $query): Builder => $query->where('last_maintenance', '<', now()->subDays(60)),
                        )->when(
                            $data['value'] === 'unknown',
                            fn (Builder $query): Builder => $query->whereNull('last_maintenance'),
                        );
                    }),
                    
                Tables\Filters\Filter::make('capacity_level')
                    ->label('Fuel Level')
                    ->form([
                        Forms\Components\Select::make('level')
                            ->options([
                                'full' => 'Full (>80%)',
                                'half' => 'Half (40-80%)',
                                'low' => 'Low (10-40%)',
                                'empty' => 'Empty (<10%)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['level'] === 'full',
                            fn (Builder $query): Builder => $query->whereRaw('(current_capacity / max_capacity) > 0.8'),
                        )->when(
                            $data['level'] === 'half',
                            fn (Builder $query): Builder => $query->whereRaw('(current_capacity / max_capacity) BETWEEN 0.4 AND 0.8'),
                        )->when(
                            $data['level'] === 'low',
                            fn (Builder $query): Builder => $query->whereRaw('(current_capacity / max_capacity) BETWEEN 0.1 AND 0.4'),
                        )->when(
                            $data['level'] === 'empty',
                            fn (Builder $query): Builder => $query->whereRaw('(current_capacity / max_capacity) < 0.1'),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('fill_truck')
                    ->label('Fill Truck')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn (FuelTruck $record): bool => 
                        $record->is_active && $record->isAvailable()
                    )
                    ->form([
                        Forms\Components\Select::make('source_storage_id')
                            ->label('Source Storage')
                            ->options(\App\Models\FuelStorage::active()->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $storage = \App\Models\FuelStorage::find($state);
                                $set('max_available', $storage?->current_capacity ?? 0);
                            }),
                        Forms\Components\TextInput::make('fill_amount')
                            ->label('Fill Amount (Liters)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(function (FuelTruck $record, Forms\Get $get): float {
                                $truckSpace = $record->available_capacity;
                                $storageAvailable = (float) $get('max_available') ?: 999999;
                                return min($truckSpace, $storageAvailable);
                            })
                            ->helperText(function (FuelTruck $record, Forms\Get $get): string {
                                $truckSpace = $record->available_capacity;
                                $storageAvailable = (float) $get('max_available') ?: 0;
                                $maxPossible = min($truckSpace, $storageAvailable);
                                return "Max possible: " . number_format($maxPossible, 0) . " L";
                            }),
                        Forms\Components\Hidden::make('max_available'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Fill reason or additional notes'),
                    ])
                    ->action(function (FuelTruck $record, array $data): void {
                        \App\Models\FuelTransaction::create([
                            'transaction_type' => \App\Models\FuelTransaction::TYPE_STORAGE_TO_TRUCK,
                            'source_storage_id' => $data['source_storage_id'],
                            'destination_truck_id' => $record->id,
                            'fuel_amount' => $data['fill_amount'],
                            'transaction_date' => now(),
                            'notes' => $data['notes'] ?? 'Quick fill via admin panel',
                            'created_by' => auth()->id(),
                            'is_approved' => true,
                        ]);
                    })
                    ->successNotificationTitle('Truck filled successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('bulk_status_change')
                        ->label('Change Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('New Status')
                                ->options(FuelTruck::getStatuses())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $records->each(function (FuelTruck $record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
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
            'index' => Pages\ListFuelTrucks::route('/'),
            'create' => Pages\CreateFuelTruck::route('/create'),
            'view' => Pages\ViewFuelTruck::route('/{record}'),
            'edit' => Pages\EditFuelTruck::route('/{record}/edit'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    
    public static function getNavigationBadge(): ?string
    {
        $maintenance = static::getModel()::where('status', FuelTruck::STATUS_MAINTENANCE)->count();
        return $maintenance > 0 ? (string) $maintenance : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $maintenance = static::getModel()::where('status', FuelTruck::STATUS_MAINTENANCE)->count();
        return $maintenance > 0 ? 'warning' : null;
    }
}