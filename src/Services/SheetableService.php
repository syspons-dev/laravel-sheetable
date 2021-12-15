<?php

namespace Syspons\Sheetable\Services;

use Exception;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Syspons\Sheetable\Models\Contracts\Sheetable;

const CACHE_KEY = 'SheetableService-Cache-Key';

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
        $this->sheetables = Cache::sear(CACHE_KEY, function () {
            $sheetables = [];
            $namespaces = config('sheetable.namespace');
            foreach (is_array($namespaces) ? $namespaces : [$namespaces] as $namespace) {
                foreach (ClassFinder::getClassesInNamespace($namespace, ClassFinder::RECURSIVE_MODE) as $class) {
                    if (in_array(Sheetable::class, class_implements($class), true)) {
                        array_push($sheetables, $class);
                    }
                }
            }
            return $sheetables;
        });
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
        $model = Str::studly(Str::singular(request()->segment($prefixLength + 2)));

        $results = preg_grep('/.*'.$model.'$/', $this->sheetables);

        return $results && 1 === count($results) ? reset($results) : null;
    }

    public function getExportExtension(): string
    {
        return strtolower(config('sheetable.export_format'));
    }
}
