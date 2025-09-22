<div>
    <livewire:project.application.preview.form :application="$application" />
    @if (count($application->additional_servers) > 0)
        <div class="pb-4">Previews will be deployed on <span
                class="dark:text-warning">{{ $application->destination->server->name }}</span>.</div>
    @endif
    <div>
        @if ($application->is_github_based())
            <div class="flex items-center gap-2">
                @can('update', $application)
                    <h3>Pull Requests on Git</h3>
                    <x-forms.button wire:click="load_prs">Load Pull Requests
                    </x-forms.button>
                @endcan
            </div>
        @endif
        @isset($rate_limit_remaining)
            <div class="pt-1 pb-4">Requests remaining till rate limited by Git: {{ $rate_limit_remaining }}</div>
        @endisset
        <div wire:loading.remove wire:target='load_prs'>
            @if ($pull_requests->count() > 0)
                <div class="overflow-x-auto table-md">
                    <table>
                        <thead>
                            <tr>
                                <th>PR Number</th>
                                <th>PR Title</th>
                                <th>Git</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pull_requests as $pull_request)
                                <tr>
                                    <th>{{ data_get($pull_request, 'number') }}</th>
                                    <td>{{ data_get($pull_request, 'title') }}</td>
                                    <td>
                                        <a target="_blank" class="text-xs"
                                            href="{{ data_get($pull_request, 'html_url') }}">Open PR on
                                            Git
                                            <x-external-link />
                                        </a>
                                    </td>
                                    <td class="flex flex-col gap-1 md:flex-row">
                                        @can('update', $application)
                                            <x-forms.button
                                                wire:click="add('{{ data_get($pull_request, 'number') }}', '{{ data_get($pull_request, 'html_url') }}')">
                                                Configure
                                            </x-forms.button>
                                        @endcan
                                        @can('deploy', $application)
                                            <x-forms.button
                                                wire:click="add_and_deploy('{{ data_get($pull_request, 'number') }}', '{{ data_get($pull_request, 'html_url') }}')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 dark:text-warning"
                                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                    fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M7 4v16l13 -8z" />
                                                </svg>Deploy
                                            </x-forms.button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @if ($application->previews->count() > 0)
        <h3 class="py-4">Deployments</h3>
        <div class="flex flex-wrap w-full gap-4">
            @foreach (data_get($application, 'previews') as $previewName => $preview)
                <div class="flex flex-col w-full p-4 border dark:border-coolgray-200"
                    wire:key="preview-container-{{ $preview->pull_request_id }}">
                    <div class="flex gap-2">PR #{{ data_get($preview, 'pull_request_id') }} |
                        @if (str(data_get($preview, 'status'))->startsWith('running'))
                            <x-status.running :status="data_get($preview, 'status')" />
                        @elseif(str(data_get($preview, 'status'))->startsWith('restarting'))
                            <x-status.restarting :status="data_get($preview, 'status')" />
                        @else
                            <x-status.stopped :status="data_get($preview, 'status')" />
                        @endif
                        @if (data_get($preview, 'status') !== 'exited')
                            | <a target="_blank" href="{{ data_get($preview, 'fqdn') }}">Open Preview
                                <x-external-link />
                            </a>
                        @endif
                        |
                        <a target="_blank" href="{{ data_get($preview, 'pull_request_html_url') }}">Open
                            PR on Git
                            <x-external-link />
                        </a>
                        @if (count($parameters) > 0)
                            |
                            <a
                                href="{{ route('project.application.deployment.index', [...$parameters, 'pull_request_id' => data_get($preview, 'pull_request_id')]) }}">
                                Deployment Logs
                            </a>
                            |
                            <a
                                href="{{ route('project.application.logs', [...$parameters, 'pull_request_id' => data_get($preview, 'pull_request_id')]) }}">
                                Application Logs
                            </a>
                        @endif
                    </div>

                    @if ($application->build_pack === 'dockercompose')
                        <div class="flex flex-col gap-4 pt-4">
                            @if (collect(json_decode($preview->docker_compose_domains))->count() === 0)
                                <form wire:submit="save_preview('{{ $preview->id }}')"
                                    class="flex items-end gap-2 pt-4">
                                    <x-forms.input label="Domain" helper="One domain per preview."
                                        id="application.previews.{{ $previewName }}.fqdn" canGate="update" :canResource="$application"></x-forms.input>
                                    @can('update', $application)
                                        <x-forms.button type="submit">Save</x-forms.button>
                                        <x-forms.button wire:click="generate_preview('{{ $preview->id }}')">Generate
                                            Domain</x-forms.button>
                                    @endcan
                                </form>
                            @else
                                @foreach (collect(json_decode($preview->docker_compose_domains)) as $serviceName => $service)
                                    <livewire:project.application.previews-compose
                                        wire:key="preview-{{ $preview->pull_request_id }}-{{ $serviceName }}"
                                        :service="$service" :serviceName="$serviceName" :preview="$preview" />
                                @endforeach
                            @endif
                        </div>
                    @else
                        <form wire:submit="save_preview('{{ $preview->id }}')" class="flex items-end gap-2 pt-4">
                            <x-forms.input label="Domain" helper="One domain per preview."
                                id="application.previews.{{ $previewName }}.fqdn" canGate="update" :canResource="$application"></x-forms.input>
                            @can('update', $application)
                                <x-forms.button type="submit">Save</x-forms.button>
                                <x-forms.button wire:click="generate_preview('{{ $preview->id }}')">Generate
                                    Domain</x-forms.button>
                            @endcan
                        </form>
                    @endif
                    <div class="flex flex-col xl:flex-row xl:items-center gap-2 pt-6">
                        <div class="flex-1"></div>
                        @can('deploy', $application)
                            <x-forms.button
                                wire:click="force_deploy_without_cache({{ data_get($preview, 'pull_request_id') }})">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path
                                        d="M12.983 8.978c3.955 -.182 7.017 -1.446 7.017 -2.978c0 -1.657 -3.582 -3 -8 -3c-1.661 0 -3.204 .19 -4.483 .515m-2.783 1.228c-.471 .382 -.734 .808 -.734 1.257c0 1.22 1.944 2.271 4.734 2.74" />
                                    <path
                                        d="M4 6v6c0 1.657 3.582 3 8 3c.986 0 1.93 -.067 2.802 -.19m3.187 -.82c1.251 -.53 2.011 -1.228 2.011 -1.99v-6" />
                                    <path d="M4 12v6c0 1.657 3.582 3 8 3c3.217 0 5.991 -.712 7.261 -1.74m.739 -3.26v-4" />
                                    <path d="M3 3l18 18" />
                                </svg>
                                Force deploy (without
                                cache)
                            </x-forms.button>
                            <x-forms.button wire:click="deploy({{ data_get($preview, 'pull_request_id') }})">
                                @if (data_get($preview, 'status') === 'exited')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 dark:text-warning"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M7 4v16l13 -8z" />
                                    </svg>
                                    Deploy
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 dark:text-orange-400"
                                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path
                                            d="M10.09 4.01l.496 -.495a2 2 0 0 1 2.828 0l7.071 7.07a2 2 0 0 1 0 2.83l-7.07 7.07a2 2 0 0 1 -2.83 0l-7.07 -7.07a2 2 0 0 1 0 -2.83l3.535 -3.535h-3.988">
                                        </path>
                                        <path d="M7.05 11.038v-3.988"></path>
                                    </svg> Redeploy
                                @endif
                            </x-forms.button>
                        @endcan
                        @if (data_get($preview, 'status') !== 'exited')
                            @can('deploy', $application)
                                <x-modal-confirmation title="Confirm Preview Deployment Stopping?" buttonTitle="Stop"
                                    submitAction="stop({{ data_get($preview, 'pull_request_id') }})" :actions="[
                                        'This preview deployment will be stopped.',
                                        'If the preview deployment is currently in use data could be lost.',
                                        'All non-persistent data of this preview deployment (containers, networks, unused images) will be deleted (don\'t worry, no data is lost and you can start the preview deployment again).',
                                    ]"
                                    :confirmWithText="false" :confirmWithPassword="false" step2ButtonText="Stop Preview Deployment">
                                    <x-slot:customButton>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-error"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path
                                                d="M6 5m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z">
                                            </path>
                                            <path
                                                d="M14 5m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z">
                                            </path>
                                        </svg>
                                        Stop
                                    </x-slot:customButton>
                                </x-modal-confirmation>
                            @endcan
                        @endif
                        @can('delete', $application)
                            <x-modal-confirmation title="Confirm Preview Deployment Deletion?" buttonTitle="Delete"
                                isErrorButton submitAction="delete({{ data_get($preview, 'pull_request_id') }})"
                                :actions="[
                                    'All containers of this preview deployment will be stopped and permanently deleted.',
                                ]" confirmationText="{{ data_get($preview, 'fqdn') . '/' }}"
                                confirmationLabel="Please confirm the execution of the actions by entering the Preview Deployment name below"
                                shortConfirmationLabel="Preview Deployment Name" :confirmWithPassword="false" />
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    
    <x-domain-conflict-modal 
        :conflicts="$domainConflicts" 
        :showModal="$showDomainConflictModal" 
        confirmAction="confirmDomainUsage">
        The preview deployment domain is already in use by other resources. Using the same domain for multiple resources can cause routing conflicts and unpredictable behavior.
        <x-slot:consequences>
            <ul class="mt-2 ml-4 list-disc">
                <li>The preview deployment may not be accessible</li>
                <li>Conflicts with production or other preview deployments</li>
                <li>SSL certificates might not work correctly</li>
                <li>Unpredictable routing behavior</li>
            </ul>
        </x-slot:consequences>
    </x-domain-conflict-modal>
</div>
