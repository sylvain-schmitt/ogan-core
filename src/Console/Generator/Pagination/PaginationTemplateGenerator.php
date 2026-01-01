<?php

/**
 * ═══════════════════════════════════════════════════════════════════════
 * 📄 PAGINATION TEMPLATE GENERATOR
 * ═══════════════════════════════════════════════════════════════════════
 */

namespace Ogan\Console\Generator\Pagination;

use Ogan\Console\Generator\AbstractGenerator;

class PaginationTemplateGenerator extends AbstractGenerator
{
    private string $modelName;
    private bool $htmx;

    public function __construct(string $modelName, bool $htmx = false)
    {
        $this->modelName = $modelName;
        $this->htmx = $htmx;
    }

    public function generate(string $projectRoot, bool $force = false): array
    {
        $generated = [];
        $skipped = [];

        $modelLower = strtolower($this->modelName);
        $templateDir = $projectRoot . '/templates/' . $modelLower;
        $this->ensureDirectory($templateDir);

        // Template principal (list.ogan)
        $listPath = $templateDir . '/list.ogan';
        if (!$this->fileExists($listPath) || $force) {
            $this->writeFile($listPath, $this->getListTemplate());
            $generated[] = "templates/{$modelLower}/list.ogan";
        } else {
            $skipped[] = "templates/{$modelLower}/list.ogan (existe déjà)";
        }

        // Template partiel (seulement si HTMX)
        if ($this->htmx) {
            $partialPath = $templateDir . '/_list_partial.ogan';
            if (!$this->fileExists($partialPath) || $force) {
                $this->writeFile($partialPath, $this->getPartialTemplate());
                $generated[] = "templates/{$modelLower}/_list_partial.ogan";
            } else {
                $skipped[] = "templates/{$modelLower}/_list_partial.ogan (existe déjà)";
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function getListTemplate(): string
    {
        $modelLower = strtolower($this->modelName);
        $modelPlural = $modelLower . 's';
        $modelTitle = ucfirst($modelPlural);

        if ($this->htmx) {
            // Version HTMX : wrapper simple qui inclut le partial
            return <<<OGAN
{{ extend('layouts/base.ogan') }}

{{ start('body') }}
<div class="max-w-6xl mx-auto py-8 px-4">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ title }}</h1>

    <!-- Zone de la liste - mise à jour par HTMX -->
    <div id="{$modelPlural}-list">
        {{ component('{$modelLower}/_list_partial', ['{$modelPlural}' => {$modelPlural}]) }}
    </div>
</div>
{{ end }}
OGAN;
        }

        // Version standard (sans HTMX)
        return <<<OGAN
{{ extend('layouts/base.ogan') }}

{{ start('body') }}
<div class="max-w-6xl mx-auto py-8 px-4">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ title }}</h1>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                {% for item in {$modelPlural} %}
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ item.createdAt }}</td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ {$modelPlural}.links()|raw }}
    </div>
</div>
{{ end }}
OGAN;
    }

    private function getPartialTemplate(): string
    {
        $modelLower = strtolower($this->modelName);
        $modelPlural = $modelLower . 's';
        $listId = $modelPlural . '-list';

        return <<<OGAN
<div id="{$listId}" data-htmx-paginated hx-boost="false">
{% if showFlashOob ?? false %}{{ component('flashes', ['oob' => true]) }}{% endif %}
<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            {% for item in {$modelPlural} %}
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ item.createdAt }}</td>
            </tr>
            {% endfor %}
        </tbody>
    </table>
</div>
<div class="mt-6">
    {{ {$modelPlural}.linksHtmx('#{$listId}')|raw }}
</div>
</div>
OGAN;
    }
}
