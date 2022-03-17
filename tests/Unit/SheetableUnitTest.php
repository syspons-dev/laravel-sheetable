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
        $withRelationDummies = WithRelationDummy::factory()->count(3)
            ->for(OneToManyRelation::factory())
            ->has(ManyToManyRelation::factory()->count(3))
            ->create();

        $this->assertEquals(3, count($withRelationDummies[0]->manyToManyRelations));
        $this->assertEquals(1, $withRelationDummies[0]->oneToManyRelation->id);
        $this->assertEquals(1, $withRelationDummies[0]->manyToManyRelations[0]->id);
        $this->assertEquals(2, $withRelationDummies[0]->manyToManyRelations[1]->id);
        $this->assertEquals(3, $withRelationDummies[0]->manyToManyRelations[2]->id);
        $this->assertEquals(1, $withRelationDummies[1]->oneToManyRelation->id);
        $this->assertEquals(4, $withRelationDummies[1]->manyToManyRelations[0]->id);
        $this->assertEquals(5, $withRelationDummies[1]->manyToManyRelations[1]->id);
        $this->assertEquals(6, $withRelationDummies[1]->manyToManyRelations[2]->id);
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

        $returnedModel1 = $spreadsheetHelper->updateOrCreate($rowArr, SimpleDummy::class);
        $this->assertCount(1, SimpleDummy::all(), '1 SimpleDummy');
        $this->assertDatabaseHas(
            'simple_dummies',
            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
        );
        $this->assertTrue('Sanchez' === $returnedModel1->lastname, 'Returned model has correct lastname');

        $dbSimpleDummy = SimpleDummy::find(1);
        $this->assertTrue($dbSimpleDummy->created_at->eq($dbSimpleDummy->updated_at), 'Created_at equals updated_at');
        $this->assertTrue($dbSimpleDummy->created_by === $dbSimpleDummy->updated_by, 'Created_by equals updated_by');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbSimpleDummy->created_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbSimpleDummy->updated_at), 'Is updated now.');

        $rowArr2 = [
            'id' => 1,
            'firstname' => 'Rick',
            'lastname' => 'Pickle',
        ];

        sleep(1);

        $returnedModel2 = $spreadsheetHelper->updateOrCreate($rowArr2, SimpleDummy::class);
        $this->assertDatabaseCount('simple_dummies', 1);
        $this->assertDatabaseHas(
            'simple_dummies',
            ['firstname' => 'Rick', 'lastname' => 'Pickle']
        );
        $this->assertTrue('Pickle' === $returnedModel2->lastname, 'Last name is Pickle');

        $dbSimpleDummy2 = SimpleDummy::find(1);
        $this->assertFalse($dbSimpleDummy->created_at->eq($dbSimpleDummy2->updated_at), 'Created_at does not equal updated_at');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbSimpleDummy2->updated_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbSimpleDummy2->created_at), 'Is created now.');
        $this->assertTrue($dbSimpleDummy2->updated_at->isAfter($dbSimpleDummy2->created_at), 'Is created now.');
    }
}
