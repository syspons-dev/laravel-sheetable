<?php

namespace Syspons\Sheetable\Tests\Unit;

use Carbon\Carbon;
use Mockery;
use Syspons\Sheetable\Helpers\SpreadsheetDropdowns;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Syspons\Sheetable\Tests\Models\Relation;
use Syspons\Sheetable\Tests\Models\RelationFactory;
use Syspons\Sheetable\Tests\Models\ModelDummy;
use Syspons\Sheetable\Tests\Models\Simple;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class SheetableUnitTest extends TestCase
{
    public function testRelations()
    {
        RelationFactory::$number = 1;

        /** @var ModelDummy $modelDummy */
        $modelDummies = ModelDummy::factory()->count(3)
            ->for(Relation::factory())
            ->has(Relation::factory()->count(3))
            ->create();

        $this->assertEquals(3, count($modelDummies[0]->relations));
        $this->assertEquals(1, $modelDummies[0]->relation->id);
        $this->assertEquals(2, $modelDummies[0]->relations[0]->id);
        $this->assertEquals(3, $modelDummies[0]->relations[1]->id);
        $this->assertEquals(4, $modelDummies[0]->relations[2]->id);
        $this->assertEquals(1, $modelDummies[1]->relation->id);
        $this->assertEquals(5, $modelDummies[1]->relations[0]->id);
        $this->assertEquals(6, $modelDummies[1]->relations[1]->id);
        $this->assertEquals(7, $modelDummies[1]->relations[2]->id);
    }

    public function testUpdateOrCreate()
    {
        $utilsMock = Mockery::mock(SpreadsheetUtils::class);
        $dropdownsMock = Mockery::mock(SpreadsheetDropdowns::class);

        $spreadsheetHelper = new SpreadsheetHelper($utilsMock, $dropdownsMock);

        $rowArr = [
            'id' => 1,
            'firstname' => 'Rick',
            'lastname' => 'Sanchez',
        ];

        $this->assertNotNull($spreadsheetHelper);

        $returnedModel1 = $spreadsheetHelper->updateOrCreate($rowArr, Simple::class);
        $this->assertCount(1, Simple::all(), '1 Simple');
        $this->assertDatabaseHas(
            'simples',
            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
        );
        $this->assertTrue('Sanchez' === $returnedModel1->lastname, 'Returned model has correct lastname');

        $dbSimple = Simple::find(1);
        $this->assertTrue($dbSimple->created_at->eq($dbSimple->updated_at), 'Created_at equals updated_at');
        $this->assertTrue($dbSimple->created_by === $dbSimple->updated_by, 'Created_by equals updated_by');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbSimple->created_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbSimple->updated_at), 'Is updated now.');

        $rowArr2 = [
            'id' => 1,
            'firstname' => 'Rick',
            'lastname' => 'Pickle',
        ];

        sleep(1);

        $returnedModel2 = $spreadsheetHelper->updateOrCreate($rowArr2, Simple::class);
        $this->assertDatabaseCount('simples', 1);
        $this->assertDatabaseHas(
            'simples',
            ['firstname' => 'Rick', 'lastname' => 'Pickle']
        );
        $this->assertTrue('Pickle' === $returnedModel2->lastname, 'Last name is Pickle');

        $dbSimple2 = Simple::find(1);
        $this->assertFalse($dbSimple->created_at->eq($dbSimple2->updated_at), 'Created_at does not equal updated_at');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbSimple2->updated_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbSimple2->created_at), 'Is created now.');
        $this->assertTrue($dbSimple2->updated_at->isAfter($dbSimple2->created_at), 'Is created now.');
    }
}
