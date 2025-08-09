<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalRequestResource\Pages;
use App\Filament\Resources\ApprovalRequestResource\RelationManagers;
use App\Models\ApprovalRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListApprovalRequests::route('/'),
            'create' => Pages\CreateApprovalRequest::route('/create'),
            'edit' => Pages\EditApprovalRequest::route('/{record}/edit'),
        ];
    }
}
