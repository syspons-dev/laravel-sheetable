<?php

namespace Syspons\Sheetable\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Syspons\Sheetable\Helpers\SpreadsheetDropdowns;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Syspons\Sheetable\Tests\Country;
use Syspons\Sheetable\Tests\CountryFactory;
use Syspons\Sheetable\Tests\ModelDummy;
use Syspons\Sheetable\Tests\TestCase;
use Syspons\Sheetable\Tests\User;

/**
 *
 */
class SheetableUnitTest extends TestCase
{

    public function  testRelations(){

        CountryFactory::$number = 1;

        /** @var ModelDummy $modelDummy */
        $modelDummies = ModelDummy::factory()->count(3)
            ->for(Country::factory())
            ->has(Country::factory()->count(3))
            ->create();

        $this->assertEquals(3, count($modelDummies[0]->countries));
        $this->assertEquals(1, $modelDummies[0]->country->id);
        $this->assertEquals(2, $modelDummies[0]->countries[0]->id);
        $this->assertEquals(3, $modelDummies[0]->countries[1]->id);
        $this->assertEquals(4, $modelDummies[0]->countries[2]->id);
        $this->assertEquals(1, $modelDummies[1]->country->id);
        $this->assertEquals(5, $modelDummies[1]->countries[0]->id);
        $this->assertEquals(6, $modelDummies[1]->countries[1]->id);
        $this->assertEquals(7, $modelDummies[1]->countries[2]->id);
    }

    public function  testUpdateOrCreate() {

        $user2 = User::factory()->make();
        $user2->setAttribute('id', 2);

        $utilsMock = Mockery::mock(SpreadsheetUtils::class);
        $dropdownsMock = Mockery::mock(SpreadsheetDropdowns::class);

        $spreadsheetHelper = new SpreadsheetHelper($utilsMock, $dropdownsMock);

        $rowArr = [
            'id' => 1,
            'firstname' => 'Rick',
            'lastname' => 'Sanchez',
        ];

        $this->assertNotNull($spreadsheetHelper);

        $returnedModel1 = $spreadsheetHelper->updateOrCreate($rowArr, User::class);
        $this->assertCount(1, User::all(), '1 User');
        $this->assertDatabaseHas(
            'users', ['firstname' => 'Rick', 'lastname' => 'Sanchez']
        );
        $this->assertTrue('Sanchez' === $returnedModel1->lastname, 'Returned model has correct lastname');

        $dbUser = User::find(1);
        $this->assertTrue($dbUser->created_at->eq($dbUser->updated_at), 'Created_at equals updated_at');
        $this->assertTrue($dbUser->created_by === $dbUser->updated_by, 'Created_by equals updated_by');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbUser->created_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-1)->isBefore($dbUser->updated_at), 'Is updated now.');

        $rowArr2 = [
            'id' => 1,
            'firstname' => 'Rick',
            'lastname' => 'Pickle',
        ];

        sleep(1);

        $returnedModel2 = $spreadsheetHelper->updateOrCreate($rowArr2, User::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas(
            'users',
            ['firstname' => 'Rick', 'lastname' => 'Pickle']);
        $this->assertTrue('Pickle' === $returnedModel2->lastname, 'Last name is Pickle');

        $dbUser2 = User::find(1);
        $this->assertFalse($dbUser->created_at->eq($dbUser2->updated_at), 'Created_at does not equal updated_at');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbUser2->updated_at), 'Is created now.');
        $this->assertTrue(Carbon::now()->addSeconds(-10)->isBefore($dbUser2->created_at), 'Is created now.');
        $this->assertTrue($dbUser2->updated_at->isAfter($dbUser2->created_at), 'Is created now.');
    }
}
