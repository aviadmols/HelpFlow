<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;

/**
 * Admin settings page for OpenRouter API key and related options.
 * Values are stored in the settings table and override .env when set.
 */
class OpenRouterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'OpenRouter / API';

    protected static ?string $title = 'OpenRouter & API Token';

    protected static string $view = 'filament.pages.settings.openrouter-settings';

    protected static ?string $slug = 'settings/openrouter';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Build the settings form. OpenRouter API key and optional default model.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('OpenRouter API')
                    ->description('Configure your OpenRouter API key here. It overrides OPENROUTER_API_KEY from .env when set. Leave empty to use .env only.')
                    ->schema([
                        TextInput::make('openrouter_api_key')
                            ->label('API Token (Key)')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-or-v1-...')
                            ->maxLength(512)
                            ->helperText('Get your key from openrouter.ai. Stored securely in the database.'),
                        TextInput::make('openrouter_default_model')
                            ->label('Default model (optional)')
                            ->placeholder('e.g. openai/gpt-4o-mini')
                            ->maxLength(255)
                            ->helperText('Override the default model from .env.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    /**
     * Load current settings into the form.
     */
    public function mount(): void
    {
        $this->form->fill([
            'openrouter_api_key' => Setting::get('openrouter_api_key') ?? '',
            'openrouter_default_model' => Setting::get('openrouter_default_model') ?? '',
        ]);
    }

    /**
     * Save settings to the database and show success notification.
     * Catches DB/cache errors and shows a clear message so save failures are visible.
     */
    public function save(): void
    {
        $this->form->validate();

        try {
            $data = $this->form->getState();
            Setting::set('openrouter_api_key', isset($data['openrouter_api_key']) && $data['openrouter_api_key'] !== '' ? $data['openrouter_api_key'] : null);
            Setting::set('openrouter_default_model', isset($data['openrouter_default_model']) && $data['openrouter_default_model'] !== '' ? $data['openrouter_default_model'] : null);

            Notification::make()
                ->title('Settings saved')
                ->body('OpenRouter API token and options have been updated.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Save failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            report($e);
        }
    }

    public static function getRoutePath(): string
    {
        return 'settings/openrouter';
    }

    /**
     * Header actions: Save button that validates form and persists settings.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
