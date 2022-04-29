<?php

namespace Syspons\Sheetable\Tests\Unit;

use Syspons\Sheetable\Tests\Models\WithRelationDummy;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class SheetableUnitTest extends TestCase
{
    public function test_relations()
    {
        $withRelationDummies = WithRelationDummy::createInstances();

        $this->assertEquals(3, count($withRelationDummies[0]->many_to_many_relations));
        $this->assertEquals(1, $withRelationDummies[0]->one_to_many_relation->id);
        $this->assertEquals(1, $withRelationDummies[0]->many_to_many_relations[0]->id);
        $this->assertEquals(2, $withRelationDummies[0]->many_to_many_relations[1]->id);
        $this->assertEquals(3, $withRelationDummies[0]->many_to_many_relations[2]->id);
        $this->assertEquals(1, $withRelationDummies[1]->one_to_many_relation->id);
        $this->assertEquals(4, $withRelationDummies[1]->many_to_many_relations[0]->id);
        $this->assertEquals(5, $withRelationDummies[1]->many_to_many_relations[1]->id);
        $this->assertEquals(6, $withRelationDummies[1]->many_to_many_relations[2]->id);
    }
}
