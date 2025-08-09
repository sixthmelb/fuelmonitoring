<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelStorageResource\Pages;
use App\Models\FuelStorage;
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
 * Filament Resource untuk Fuel Storage Management
 * 
 * Features:
 * - Real-time capacity monitoring dengan progress bars
 * - Alert indicators untuk storage di bawah threshold
 * - Capacity calculator dan validator
 * - Interactive charts untuk capacity trends
 */
class FuelStorageResource extends Resource
{
    protected static ?string $model = FuelStorage::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    
    protected static ?string $navigationLabel = 'Fuel Storages';
    
    protected static ?string $modelLabel = 'Fuel Storage';
    
    protected static ?string $pluralModelLabel = 'Fuel Storages';
    
    protected static ?string $navigationGroup = 'Fuel Management';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Storage Information')
                    ->description('Basic information about the fuel storage')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->placeholder('e.g., Main Fuel Storage A'),
                                    
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., ST-001')
                                    ->rules(['regex:/^[A-Z]{2}-\d{3}$/'])
                                    ->validationMessages([
                                        'regex' => 'Code must follow format: ST-001',
                                    ]),
                            ]),
                            
                        Forms\Components\TextInput::make('location')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Mining Site A - North Sector'),
                            
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Optional description of the storage facility'),
                    ]),
                    
                Forms\Components\Section::make('Capacity Configuration')
                    ->description('Set storage capacity limits and thresholds')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('max_capacity')
                                    ->label('Maximum Capacity (Liters)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(1000000)
                                    ->step(100)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        // Auto-calculate min_threshold if not set (10% of max)
                                        if ($state && !$get('min_threshold')) {
                                            $set('min_threshold', $state * 0.1);
                                        }
                                        
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
                                    
                                Forms\Components\TextInput::make('min_threshold')
                                    ->label('Minimum Threshold (Liters)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $max = $get('max_capacity');
                                        if ($max && $state > $max) {
                                            $set('min_threshold', $max * 0.1);
                                        }
                                    })
                                    ->helperText('Alert will be triggered when capacity falls below this level')
                                    ->suffix('L'),
                            ]),
                            
                        // Capacity Calculator Preview
                        Forms\Components\Placeholder::make('capacity_preview')
                            ->label('Capacity Preview')
                            ->content(function (Forms\Get $get): string {
                                $max = (float) $get('max_capacity');
                                $current = (float) $get('current_capacity');
                                $threshold = (float) $get('min_threshold');
                                
                                if (!$max) return 'Enter maximum capacity to see preview';
                                
                                $percentage = $max > 0 ? ($current / $max) * 100 : 0;
                                $available = $max - $current;
                                $belowThreshold = $current <= $threshold;
                                
                                $status = match(true) {
                                    $percentage >= 80 => '🟢 Optimal',
                                    $percentage >= 50 => '🟡 Good',
                                    $percentage >= 20 => '🟠 Low',
                                    default => '🔴 Critical'
                                };
                                
                                return "
                                    <div class='space-y-2'>
                                        <div><strong>Status:</strong> {$status}</div>
                                        <div><strong>Usage:</strong> " . number_format($percentage, 1) . "%</div>
                                        <div><strong>Available:</strong> " . number_format($available, 0) . " L</div>
                                        " . ($belowThreshold ? "<div class='text-red-600'><strong>⚠️ Below Threshold!</strong></div>" : "") . "
                                    </div>
                                ";
                            })
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive storages will not appear in transaction options'),
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
                    ->label('Storage Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
                    
                // Interactive Capacity Bar
                Tables\Columns\ViewColumn::make('capacity_bar')
                    ->label('Capacity')
                    ->view('filament.columns.capacity-bar')
                    ->sortable(['current_capacity']),
                    
                Tables\Columns\TextColumn::make('capacity_text')
                    ->label('Details')
                    ->getStateUsing(function (FuelStorage $record): string {
                        $percentage = $record->capacity_percentage;
                        $current = number_format($record->current_capacity, 0);
                        $max = number_format($record->max_capacity, 0);
                        
                        return "{$current}L / {$max}L (" . number_format($percentage, 1) . "%)";
                    })
                    ->badge()
                    ->color(function (FuelStorage $record): string {
                        $percentage = $record->capacity_percentage;
                        return match(true) {
                            $percentage >= 80 => 'success',
                            $percentage >= 50 => 'warning',
                            $percentage >= 20 => 'danger',
                            default => 'gray'
                        };
                    }),
                    
                Tables\Columns\IconColumn::make('status_indicator')
                    ->label('Status')
                    ->getStateUsing(function (FuelStorage $record): string {
                        if (!$record->is_active) return 'inactive';
                        if ($record->isBelowThreshold()) return 'alert';
                        return 'normal';
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'inactive' => 'heroicon-o-pause-circle',
                        'alert' => 'heroicon-o-exclamation-triangle',
                        'normal' => 'heroicon-o-check-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inactive' => 'gray',
                        'alert' => 'danger',
                        'normal' => 'success',
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'below_threshold' => 'Below Threshold',
                        'critical' => 'Critical (< 5%)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'active',
                            fn (Builder $query): Builder => $query->where('is_active', true),
                        )->when(
                            $data['value'] === 'inactive',
                            fn (Builder $query): Builder => $query->where('is_active', false),
                        )->when(
                            $data['value'] === 'below_threshold',
                            fn (Builder $query): Builder => $query->whereRaw('current_capacity <= min_threshold'),
                        )->when(
                            $data['value'] === 'critical',
                            fn (Builder $query): Builder => $query->whereRaw('(current_capacity / max_capacity) <= 0.05'),
                        );
                    }),
                    
                Tables\Filters\Filter::make('capacity_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_percentage')
                                    ->label('Min Capacity %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                                Forms\Components\TextInput::make('max_percentage')
                                    ->label('Max Capacity %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_percentage'],
                                fn (Builder $query, $percentage): Builder => $query->whereRaw(
                                    '(current_capacity / max_capacity) * 100 >= ?', 
                                    [$percentage]
                                ),
                            )
                            ->when(
                                $data['max_percentage'],
                                fn (Builder $query, $percentage): Builder => $query->whereRaw(
                                    '(current_capacity / max_capacity) * 100 <= ?', 
                                    [$percentage]
                                ),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('quick_refill')
                    ->label('Quick Refill')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (FuelStorage $record): bool => $record->is_active)
                    ->form([
                        Forms\Components\TextInput::make('refill_amount')
                            ->label('Refill Amount (Liters)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (FuelStorage $record): float => $record->available_capacity)
                            ->helperText(fn (FuelStorage $record): string => 
                                'Available space: ' . number_format($record->available_capacity, 0) . ' L'
                            ),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Refill reason or vendor information'),
                    ])
                    ->action(function (FuelStorage $record, array $data): void {
                        // Create vendor to storage transaction
                        \App\Models\FuelTransaction::create([
                            'transaction_type' => \App\Models\FuelTransaction::TYPE_VENDOR_TO_STORAGE,
                            'destination_storage_id' => $record->id,
                            'fuel_amount' => $data['refill_amount'],
                            'transaction_date' => now(),
                            'notes' => $data['notes'] ?? 'Quick refill via admin panel',
                            'created_by' => auth()->id(),
                            'is_approved' => true,
                        ]);
                    })
                    ->successNotificationTitle('Storage refilled successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('toggle_active')
                        ->label('Toggle Active Status')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each(function (FuelStorage $record) {
                                $record->update(['is_active' => !$record->is_active]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Storage Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->badge()
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('name')
                                    ->weight('bold'),
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
                        Infolists\Components\TextEntry::make('location')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),
                    
                Infolists\Components\Section::make('Capacity Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('max_capacity')
                                    ->label('Maximum Capacity')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                                    ->badge()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('current_capacity')
                                    ->label('Current Capacity')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                                    ->badge()
                                    ->color('success'),
                            ]),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('min_threshold')
                                    ->label('Minimum Threshold')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                                    ->badge()
                                    ->color('warning'),
                                Infolists\Components\TextEntry::make('available_capacity')
                                    ->label('Available Space')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                                    ->badge()
                                    ->color('info'),
                            ]),
                        Infolists\Components\TextEntry::make('capacity_percentage')
                            ->label('Capacity Usage')
                            ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                            ->badge()
                            ->color(fn ($state) => match(true) {
                                $state >= 80 => 'success',
                                $state >= 50 => 'warning',
                                $state >= 20 => 'danger',
                                default => 'gray'
                            }),
                    ]),
                    
                Infolists\Components\Section::make('Activity Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
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
            'index' => Pages\ListFuelStorages::route('/'),
            'create' => Pages\CreateFuelStorage::route('/create'),
            //'view' => Pages\ViewFuelStorage::route('/{record}'),
            'edit' => Pages\EditFuelStorage::route('/{record}/edit'),
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
        $belowThreshold = static::getModel()::belowThreshold()->count();
        return $belowThreshold > 0 ? (string) $belowThreshold : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $belowThreshold = static::getModel()::belowThreshold()->count();
        return $belowThreshold > 0 ? 'danger' : null;
    }
}