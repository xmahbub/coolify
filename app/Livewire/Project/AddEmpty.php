<?php

namespace App\Livewire\Project;

use App\Models\Project;
use App\Support\ValidationPatterns;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

class AddEmpty extends Component
{
    public string $name;

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ValidationPatterns::nameRules(),
            'description' => ValidationPatterns::descriptionRules(),
        ];
    }

    protected function messages(): array
    {
        return ValidationPatterns::combinedMessages();
    }

    public function submit()
    {
        try {
            $this->validate();
            $project = Project::create([
                'name' => $this->name,
                'description' => $this->description,
                'team_id' => currentTeam()->id,
                'uuid' => (string) new Cuid2,
            ]);

            return redirect()->route('project.show', $project->uuid);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }
}
