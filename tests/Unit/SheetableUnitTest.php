<?php

namespace Syspons\Sheetable\Tests\Unit;

use Carbon\Carbon;
use Mockery;
use Syspons\Sheetable\Helpers\SpreadsheetDropdowns;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Syspons\Sheetable\Tests\Models\ManyToManyRelation;
use Syspons\Sheetable\Tests\Models\OneToManyRelation;
use Syspons\Sheetable\Tests\Models\WithRelationDummy;
use Syspons\Sheetable\Tests\Models\SimpleDummy;
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

    public function testUpdateOrCreate()
    {
        $utilsMock = Mockery::mock(SpreadsheetUtils::class);
        $dropdownsMock = Mockery::mock(SpreadsheetDropdowns::class);

        $spreadsheetHelper = new SpreadsheetHelper($utilsMock, $dropdownsMock);

        $rowArr = [
            'id' => 1,
            'title' => 'Test',
        ];

        $this->assertNotNull($spreadsheetHelper);

        $returnedModel1 = $spreadsheetHelper->updateOrCreate($rowArr, SimpleDummy::class);
        $this->assertCount(1, SimpleDummy::all(), '1 SimpleDummy');
        $this->assertDatabaseHas(
            'simple_dummies',
            ['title' => 'Test']
        );
        $this->assertTrue('Test' === $returnedModel1->title, 'Returned model has correct title');


        $rowArr2 = [
            'id' => 1,
            'title' => 'Changed',
        ];

        sleep(1);

        $returnedModel2 = $spreadsheetHelper->updateOrCreate($rowArr2, SimpleDummy::class);
        $this->assertDatabaseCount('simple_dummies', 1);
        $this->assertDatabaseHas(
            'simple_dummies',
            ['title' => 'Changed']
        );
        $this->assertTrue('Changed' === $returnedModel2->title, 'Title changed');
    }
}
