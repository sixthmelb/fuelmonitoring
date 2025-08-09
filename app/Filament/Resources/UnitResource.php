<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
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
 * Filament Resource untuk Unit Management
 * 
 * Features:
 * - Unit tracking dengan KM/HM monitoring
 * - Fuel consumption analysis
 * - Maintenance scheduling
 * - Operator management
 * - Real-time efficiency metrics
 */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Units';
    
    protected static ?string $modelLabel = 'Unit';
    
    protected static ?string $pluralModelLabel = 'Units';
    
    protected static ?string $navigationGroup = 'Fleet Management';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Unit Identification')
                    ->description('Basic unit information and classification')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Unit Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., DT-001, EX-002')
                                    ->rules(['regex:/^[A-Z]{2}-\d{3}$/'])
                                    ->validationMessages([
                                        'regex' => 'Code must follow format: DT-001 or EX-002',
                                    ]),
                                    
                                Forms\Components\TextInput::make('name')
                                    ->label('Unit Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Dump Truck CAT 777D'),
                                    
                                Forms\Components\Select::make('type')
                                    ->label('Unit Type')
                                    ->required()
                                    ->options(Unit::getTypes())
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        // Auto-suggest based on type
                                        if ($state === Unit::TYPE_DUMP_TRUCK) {
                                            $set('fuel_tank_capacity', 1500);
                                        } elseif ($state === Unit::TYPE_EXCAVATOR) {
                                            $set('fuel_tank_capacity', 1200);
                                        } elseif ($state === Unit::TYPE_DOZER) {
                                            $set('fuel_tank_capacity', 950);
                                        }
                                    }),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Equipment Details')
                    ->description('Technical specifications and manufacturer information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('brand')
                                    ->label('Brand')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Caterpillar, Komatsu'),
                                    
                                Forms\Components\TextInput::make('model')
                                    ->label('Model')
                                    ->maxLength(255)
                                    ->placeholder('e.g., 777D, PC800'),
                                    
                                Forms\Components\TextInput::make('year')
                                    ->label('Year')
                                    ->numeric()
                                    ->minValue(1990)
                                    ->maxValue(date('Y') + 1)
                                    ->placeholder('Manufacturing year'),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('engine_capacity')
                                    ->label('Engine Capacity (CC)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('CC')
                                    ->placeholder('Engine displacement'),
                                    
                                Forms\Components\TextInput::make('fuel_tank_capacity')
                                    ->label('Fuel Tank Capacity (Liters)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('L')
                                    ->placeholder('Maximum fuel capacity'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Current Status & Metrics')
                    ->description('Real-time operational data')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('current_km')
                                    ->label('Current KM')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.1)
                                    ->suffix('KM')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $lastService = $get('last_service_km');
                                        if ($lastService && $state && $state > $lastService) {
                                            $kmSince = $state - $lastService;
                                            $set('km_since_service', $kmSince);
                                        }
                                    })
                                    ->visible(fn (Forms\Get $get): bool => 
                                        in_array($get('type'), [Unit::TYPE_DUMP_TRUCK, Unit::TYPE_LOADER])
                                    ),
                                    
                                Forms\Components\TextInput::make('current_hm')
                                    ->label('Current HM (Hour Meter)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.1)
                                    ->suffix('HM')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $lastService = $get('last_service_hm');
                                        if ($lastService && $state && $state > $lastService) {
                                            $hmSince = $state - $lastService;
                                            $set('hm_since_service', $hmSince);
                                        }
                                    }),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('last_service_km')
                                    ->label('Last Service KM')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('KM')
                                    ->visible(fn (Forms\Get $get): bool => 
                                        in_array($get('type'), [Unit::TYPE_DUMP_TRUCK, Unit::TYPE_LOADER])
                                    ),
                                    
                                Forms\Components\TextInput::make('last_service_hm')
                                    ->label('Last Service HM')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('HM'),
                            ]),
                            
                        // Service interval display
                        Forms\Components\Placeholder::make('service_interval')
                            ->label('Service Interval Status')
                            ->content(function (Forms\Get $get): string {
                                $currentKm = (float) $get('current_km');
                                $lastServiceKm = (float) $get('last_service_km');
                                $currentHm = (float) $get('current_hm');
                                $lastServiceHm = (float) $get('last_service_hm');
                                
                                $kmSince = $currentKm && $lastServiceKm ? $currentKm - $lastServiceKm : 0;
                                $hmSince = $currentHm && $lastServiceHm ? $currentHm - $lastServiceHm : 0;
                                
                                $status = '';
                                if ($kmSince > 0) {
                                    $status .= "KM since service: " . number_format($kmSince, 1) . " KM\n";
                                    if ($kmSince > 5000) $status .= "⚠️ Service due soon (KM)\n";
                                }
                                if ($hmSince > 0) {
                                    $status .= "HM since service: " . number_format($hmSince, 1) . " HM\n";
                                    if ($hmSince > 500) $status .= "⚠️ Service due soon (HM)\n";
                                }
                                
                                return $status ?: 'Enter service data to see interval status';
                            })
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Operations')
                    ->description('Current operational assignment and location')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('operator_name')
                                    ->label('Current Operator')
                                    ->maxLength(255)
                                    ->placeholder('Operator name'),
                                    
                                Forms\Components\TextInput::make('location')
                                    ->label('Current Location')
                                    ->maxLength(255)
                                    ->placeholder('e.g., North Pit, South Sector'),
                                    
                                Forms\Components\Select::make('status')
                                    ->label('Operational Status')
                                    ->required()
                                    ->options(Unit::getStatuses())
                                    ->default(Unit::STATUS_ACTIVE),
                            ]),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive units will not appear in transaction options'),
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
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => 
                        Unit::getTypes()[$state] ?? $state
                    )
                    ->color(fn (string $state): string => match($state) {
                        Unit::TYPE_DUMP_TRUCK => 'primary',
                        Unit::TYPE_EXCAVATOR => 'success',
                        Unit::TYPE_DOZER => 'warning',
                        Unit::TYPE_LOADER => 'info',
                        default => 'gray'
                    })
                    ->icon(fn (string $state): string => match($state) {
                        Unit::TYPE_DUMP_TRUCK => 'heroicon-o-truck',
                        Unit::TYPE_EXCAVATOR => 'heroicon-o-wrench-screwdriver',
                        Unit::TYPE_DOZER => 'heroicon-o-squares-2x2',
                        Unit::TYPE_LOADER => 'heroicon-o-cube',
                        default => 'heroicon-o-cog-6-tooth'
                    }),
                    
                Tables\Columns\TextColumn::make('brand_model')
                    ->label('Brand/Model')
                    ->getStateUsing(function (Unit $record): string {
                        $parts = array_filter([$record->brand, $record->model]);
                        return implode(' ', $parts) ?: 'N/A';
                    })
                    ->searchable(['brand', 'model'])
                    ->toggleable(),
                    
                Tables\Columns\ViewColumn::make('metrics')
                    ->label('Current Metrics')
                    ->view('filament.columns.unit-metrics'),
                    
                Tables\Columns\ViewColumn::make('efficiency')
                    ->label('Efficiency')
                    ->view('filament.columns.unit-efficiency')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operator')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => 
                        Unit::getStatuses()[$state] ?? $state
                    )
                    ->color(fn (string $state): string => match($state) {
                        Unit::STATUS_ACTIVE => 'success',
                        Unit::STATUS_MAINTENANCE => 'warning',
                        Unit::STATUS_STANDBY => 'info',
                        Unit::STATUS_OUT_OF_SERVICE => 'danger',
                        default => 'gray'
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
                
                Tables\Filters\SelectFilter::make('type')
                    ->options(Unit::getTypes())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options(Unit::getStatuses())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('brand')
                    ->options(fn (): array => 
                        Unit::whereNotNull('brand')
                            ->distinct()
                            ->pluck('brand', 'brand')
                            ->toArray()
                    )
                    ->searchable(),
                    
                Tables\Filters\Filter::make('service_due')
                    ->label('Service Due')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function ($q) {
                            $q->whereRaw('(current_km - last_service_km) > 5000')
                              ->orWhereRaw('(current_hm - last_service_hm) > 500');
                        });
                    })
                    ->toggle(),
                    
                Tables\Filters\Filter::make('high_usage')
                    ->label('High Usage Units')
                    ->query(function (Builder $query): Builder {
                        return $query->where('current_km', '>', 100000)
                                    ->orWhere('current_hm', '>', 10000);
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('update_metrics')
                    ->label('Update Metrics')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('new_km')
                                    ->label('New KM Reading')
                                    ->numeric()
                                    ->minValue(fn (Unit $record): float => $record->current_km)
                                    ->helperText(fn (Unit $record): string => 
                                        'Current: ' . number_format($record->current_km, 1) . ' KM'
                                    )
                                    ->visible(fn (Unit $record): bool => 
                                        in_array($record->type, [Unit::TYPE_DUMP_TRUCK, Unit::TYPE_LOADER])
                                    ),
                                    
                                Forms\Components\TextInput::make('new_hm')
                                    ->label('New HM Reading')
                                    ->numeric()
                                    ->minValue(fn (Unit $record): float => $record->current_hm)
                                    ->helperText(fn (Unit $record): string => 
                                        'Current: ' . number_format($record->current_hm, 1) . ' HM'
                                    ),
                            ]),
                        Forms\Components\Textarea::make('notes')
                            ->label('Update Notes')
                            ->placeholder('Reason for metric update'),
                    ])
                    ->action(function (Unit $record, array $data): void {
                        $updates = [];
                        if (isset($data['new_km']) && $data['new_km'] > $record->current_km) {
                            $updates['current_km'] = $data['new_km'];
                        }
                        if (isset($data['new_hm']) && $data['new_hm'] > $record->current_hm) {
                            $updates['current_hm'] = $data['new_hm'];
                        }
                        
                        if (!empty($updates)) {
                            $record->update($updates);
                        }
                    })
                    ->successNotificationTitle('Metrics updated successfully'),
                    
                Tables\Actions\Action::make('schedule_service')
                    ->label('Schedule Service')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->form([
                        Forms\Components\DatePicker::make('service_date')
                            ->label('Service Date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('service_notes')
                            ->label('Service Notes')
                            ->placeholder('Service work to be performed'),
                    ])
                    ->action(function (Unit $record, array $data): void {
                        $record->update([
                            'status' => Unit::STATUS_MAINTENANCE,
                            'last_service_km' => $record->current_km,
                            'last_service_hm' => $record->current_hm,
                        ]);
                    })
                    ->successNotificationTitle('Service scheduled'),
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
                                ->options(Unit::getStatuses())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $records->each(function (Unit $record) use ($data) {
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
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'view' => Pages\ViewUnit::route('/{record}'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
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
        $maintenance = static::getModel()::where('status', Unit::STATUS_MAINTENANCE)->count();
        return $maintenance > 0 ? (string) $maintenance : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $maintenance = static::getModel()::where('status', Unit::STATUS_MAINTENANCE)->count();
        return $maintenance > 0 ? 'warning' : null;
    }
}