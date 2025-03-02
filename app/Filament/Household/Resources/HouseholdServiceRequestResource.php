<?php

namespace App\Filament\Household\Resources;

use App\Filament\Household\Resources\HouseholdServiceRequestResource\Pages;
use App\Filament\Household\Resources\HouseholdServiceRequestResource\RelationManagers\BidsRelationManager;
use App\Models\AccountInformation;
use App\Models\Company;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class HouseholdServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Request Waste Service';

    protected static ?string $navigationLabel = 'Request Waste Service';
    protected static ?string $navigationGroup = 'Requests Management';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('household_id', auth()?->getUser()?->id ?? null);
    }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request Details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('waste_type')
                        ->label('Waste Type')
                        ->required()
                        ->options([
                            'organic' => 'Organic Waste',
                            'recyclable' => 'Recyclable Waste',
                            'e-waste' => 'Electronic Waste',
                            'hazardous' => 'Hazardous Waste',
                        ]),
                    Forms\Components\Select::make('quantity')
                        ->label('Quantity')
                        ->options([
                            '1-5kg' => '1 - 5 kg',
                            '6-10kg' => '6 - 10 kg',
                            '11-20kg' => '11 - 20 kg',
                            '21-50kg' => '21 - 50 kg',
                            'above_50kg' => 'Above 50 kg',
                        ])
                        ->required(),
                    Forms\Components\FileUpload::make('waste_photos')
                        ->label('Upload Waste Photos')
                        ->helperText('Upload photos of the waste to help companies assess and provide accurate quotes')
                        ->multiple()
                        ->disk('public')
                        ->directory('waste-photos')
                        ->preserveFilenames()
                        ->required()
                        ->columnSpan(2),
                    Forms\Components\Textarea::make('description')
                        ->label('Additional Details')
                        ->helperText('Provide any additional information about the waste or special handling requirements')
                        ->columnSpan(2)
                        ->maxLength(1000),
                    Forms\Components\DatePicker::make('preferred_date')
                        ->label('Preferred Collection Date')
                        ->required(),
                    Forms\Components\TimePicker::make('preferred_time')
                        ->label('Preferred Collection Time')
                        ->required(),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('waste_type')
                    ->searchable()
                    ->color(fn(?Model $record): array => \Filament\Support\Colors\Color::hex(optional($record->tenant)->color ?? '#22e03a'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('quantity')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('accepted_company_id')
                    ->formatStateUsing(fn($state): string => Company::find((int) $state)?->company_name ?? 'Pending Company') // Cast to int if needed
                    ->label('Assigned Company')
                    ->badge()
                    ->default('pending')
                    ->colors([
                        'success' => 'confirmed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'assigned' => 'warning',
                        'accepted' => 'success',
                        'awaiting_payment' => 'warning',
                        'payment_sent' => 'info',
                        'in_progress' => 'info',
                        'payment_released' => 'info',
                        'completed' => 'success',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('preferred_date')
                    ->date(),
                Tables\Columns\TextColumn::make('preferred_time')
                    ->time(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'accepted' => 'Accepted',

                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ])
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Available Companies'),

                Tables\Actions\Action::make('view_account')
                    ->label('Make Payment')
                    ->visible(fn($record) => $record->accepted_company_id)
                    ->modalHeading('Payment Gateway')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Medium)
                    ->form([
                        Forms\Components\Placeholder::make('amount_to_pay')
                            ->label('Amount to Pay')
                            ->content(fn($record) => '₦' . number_format($record->final_amount, 2)),
                        Forms\Components\Placeholder::make('company_name')
                            ->label('Service Provider')
                            ->content(fn($record) => Company::find($record->accepted_company_id)?->company_name ?? 'Unknown'),
                        Forms\Components\Select::make('payment_method')
                            ->label('Select Payment Method')
                            ->options([
                                'credit_card' => 'Credit/Debit Card',
                                'bank_transfer' => 'Bank Transfer',
                                // 'ussd' => 'USSD Payment',
                            ])
                            ->reactive()
                            ->required(),
                        Forms\Components\Grid::make()
                            ->schema(fn (Forms\Get $get) => match ($get('payment_method')) {
                                'credit_card' => [
                                    Forms\Components\TextInput::make('card_number')
                                        ->label('Card Number')
                                        ->placeholder('**** **** **** ****')
                                        ->mask('9999 9999 9999 9999')
                                        ->required(),
                                    Forms\Components\TextInput::make('expiry')
                                        ->label('Expiry Date')
                                        ->placeholder('MM/YY')
                                        ->mask('99/99')
                                        ->required(),
                                    Forms\Components\TextInput::make('cvv')
                                        ->label('CVV')
                                        ->placeholder('***')
                                        ->mask('999')
                                        ->required(),
                                    Forms\Components\TextInput::make('card_name')
                                        ->label('Name on Card')
                                        ->required(),
                                ],
                                'bank_transfer' => [
                                    Forms\Components\Placeholder::make('bank_details')
                                        ->label('Bank Transfer Details')
                                        ->content(function ($record) {
                                            $account = AccountInformation::where('status', 'open')->first();
                                            
                                            if (!$account) {
                                                return '<div class="text-danger">No bank account is currently available for payment.</div>';
                                            }

                                            return view('filament.resources.account-modal', [
                                                'account' => $account,
                                                'amount' => number_format($record->final_amount, 2),
                                                'reference' => 'GC' . $record->id,
                                            ]);
                                        }),
                                ],
                                // 'ussd' => [
                                //     Forms\Components\Placeholder::make('ussd_code')
                                //         ->label('USSD Payment Instructions')
                                //         ->content(function ($record) {
                                //             return "
                                //                 <div class='space-y-2'>
                                //                     <p class='font-medium'>Follow these steps:</p>
                                //                     <ol class='list-decimal list-inside space-y-1'>
                                //                         <li>Dial *123*12345#</li>
                                //                         <li>Select Option 1 (Payment)</li>
                                //                         <li>Enter Amount: {$record->final_amount}</li>
                                //                         <li>Enter Reference: GC{$record->id}</li>
                                //                         <li>Confirm payment with your PIN</li>
                                //                     </ol>
                                //                 </div>
                                //             ";
                                //         })->extraAttributes(['class' => 'prose']),
                                // ],
                                default => [],
                            }),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        // Simulate payment processing delay
                        sleep(2);
                        
                        Payment::create([
                            'service_request_id' => $record->id,
                            'method' => $data['payment_method'],
                            'amount' => $record->final_amount,
                            'status' => 'confirmed',
                            'paid_at' => now(),
                        ]);
                        
                        $record->update([
                            'status' => 'paid',
                            'payment_status' => 'confirmed',
                            'payment_received_at' => now(),
                        ]);
                        
                        Notification::make()
                            ->title('Payment Successful')
                            ->body('Your payment has been processed successfully. The service provider will begin work shortly.')
                            ->success()
                            ->send();
                    })
                    ->modalButton('Complete Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('primary'),

                Tables\Actions\Action::make('upload_completion_photos')
                    ->visible(fn($record) => $record->status === 'completed')
                    ->icon('heroicon-o-camera')
                    ->form([
                        Forms\Components\FileUpload::make('household_completion_photos')
                            ->label('Upload Service Completion Photos')
                            ->multiple()
                            ->disk('public')
                            ->directory('household-completion-photos')
                            ->preserveFilenames()
                            ->required(),
                        Forms\Components\Textarea::make('household_completion_notes')
                            ->label('Add Your Notes (Optional)')
                            ->maxLength(500),
                    ])
                    ->action(function (ServiceRequest $record, array $data): void {
                        $photos = $data['household_completion_photos'];
                        
                        // Ensure we're working with an array
                        if (!is_array($photos)) {
                            $photos = [$photos];
                        }
                        
                        $record->update([
                            'household_completion_photos' => $photos,
                            'household_completion_notes' => $data['household_completion_notes'] ?? null,
                        ]);
                        
                        Notification::make()
                            ->title('Photos Uploaded Successfully')
                            ->success()
                            ->send();
                    }),

                // Tables\Actions\Action::make('cancel_request')
                //     ->visible(fn($record) => in_array($record->status, ['accepted', 'assigned']))
                //     ->requiresConfirmation()
                //     ->action(fn(ServiceRequest $record) => $record->update(['status' => 'cancelled'])),
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
            BidsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceRequests::route('/'),
            'create' => Pages\CreateServiceRequest::route('/create'),
            'view' => Pages\ViewServiceRequest::route('/{record}'),
            'edit' => Pages\EditServiceRequest::route('/{record}/edit'),
        ];
    }
}