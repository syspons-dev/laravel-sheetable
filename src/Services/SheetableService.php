<?php

namespace Syspons\Sheetable\Services;

use Exception;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SheetableService
{
    /**
     * @var Model[]
     */
    private array $sheetables;

    /**
     * The Constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->initSheetableClasses();
    }

    /**
     * Get the sheetable classes collection.
     *
     * @return Model[]
     */
    public function getSheetableClasses(): array
    {
        return $this->sheetables;
    }

    /**
     * Initialize the sheetable classes collection.
     *
     * @throws Exception
     */
    private function initSheetableClasses(): void
    {
        $sheetables = [];
        $namespaces = config('sheetable.namespace');
        foreach (is_array($namespaces) ? $namespaces : [$namespaces] as $namespace) {
            foreach (ClassFinder::getClassesInNamespace($namespace) as $class) {
                if (in_array(Sheetable::class, class_implements($class), true)) {
                    array_push($sheetables, $class);
                }
            }
        }
        $this->sheetables = $sheetables;
    }

    /**
     * Get the target model.
     */
    public function getModelClassFromRequest(): string|Sheetable|Model|null
    {
        if (!request()->segments() || !$this->sheetables) {
            return null;
        }

        $prefixLength = count(explode('/', config('sheetable.prefix')));
        $model = Str::studly(Str::singular(request()->segment($prefixLength + 1)));

        $results = preg_grep('/.*'.$model.'$/', $this->sheetables);

        return $results && 1 === count($results) ? $results[0] : null;
    }

    public function getExportExtension(): string
    {
        return strtolower(config('sheetable.export_format'));
    }
}
