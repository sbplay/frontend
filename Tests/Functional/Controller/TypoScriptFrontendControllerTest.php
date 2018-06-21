<?php
namespace TYPO3\CMS\Frontend\Tests\Functional\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class TypoScriptFrontendControllerTest extends FunctionalTestCase
{
    /**
     * @var TypoScriptFrontendController
     */
    protected $tsFrontendController;

    protected function setUp()
    {
        parent::setUp();
        $this->importDataSet(__DIR__ . '/fixtures.xml');

        $this->tsFrontendController = $this->getAccessibleMock(
            TypoScriptFrontendController::class,
            ['dummy'],
            [],
            '',
            false
        );

        $pageContextMock = $this->getMockBuilder(\TYPO3\CMS\Frontend\Page\PageRepository::class)->getMock();
        $this->tsFrontendController->_set('sys_page', $pageContextMock);
    }

    /**
     * @test
     */
    public function getFirstTimeValueForRecordReturnCorrectData()
    {
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('tt_content:2', 1),
            2,
            'The next start/endtime should be 2'
        );
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('tt_content:2', 2),
            3,
            'The next start/endtime should be 3'
        );
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('tt_content:2', 4),
            5,
            'The next start/endtime should be 5'
        );
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('tt_content:2', 5),
            PHP_INT_MAX,
            'The next start/endtime should be PHP_INT_MAX as there are no more'
        );
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('tt_content:3', 1),
            PHP_INT_MAX,
            'Should be PHP_INT_MAX as table has not this PID'
        );
        $this->assertSame(
            $this->getFirstTimeValueForRecordCall('fe_groups:2', 1),
            PHP_INT_MAX,
            'Should be PHP_INT_MAX as table fe_groups has no start/endtime in TCA'
        );
    }

    /**
     * @param string $currentDomain
     * @test
     * @dataProvider getSysDomainCacheDataProvider
     */
    public function getSysDomainCacheReturnsCurrentDomainRecord($currentDomain)
    {
        GeneralUtility::flushInternalRuntimeCaches();

        $_SERVER['HTTP_HOST'] = $currentDomain;
        $domainRecords = [
            'typo3.org' => [
                'uid' => '1',
                'pid' => '1',
                'domainName' => 'typo3.org',
            ],
            'foo.bar' => [
                'uid' => '2',
                'pid' => '1',
                'domainName' => 'foo.bar',
            ],
            'example.com' => [
                'uid' => '3',
                'pid' => '1',
                'domainName' => 'example.com',
            ],
        ];

        $connection = (new ConnectionPool())->getConnectionForTable('sys_domain');

        $sqlServerIdentityDisabled = false;
        if ($connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            $connection->exec('SET IDENTITY_INSERT sys_domain ON');
            $sqlServerIdentityDisabled = true;
        }

        foreach ($domainRecords as $domainRecord) {
            $connection->insert(
                'sys_domain',
                $domainRecord
            );
        }

        if ($sqlServerIdentityDisabled) {
            $connection->exec('SET IDENTITY_INSERT sys_domain OFF');
        }

        GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_runtime')->flush();
        $expectedResult = [
            $domainRecords[$currentDomain]['pid'] => $domainRecords[$currentDomain],
        ];

        $actualResult = $this->tsFrontendController->_call('getSysDomainCache');
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @param string $tablePid
     * @param int $now
     * @return int
     */
    public function getFirstTimeValueForRecordCall($tablePid, $now)
    {
        return $this->tsFrontendController->_call('getFirstTimeValueForRecord', $tablePid, $now);
    }

    /**
     * @return array
     */
    public function getSysDomainCacheDataProvider()
    {
        return [
            'typo3.org' => [
                'typo3.org',
            ],
            'foo.bar' => [
                'foo.bar',
            ],
            'example.com' => [
                'example.com',
            ],
        ];
    }
}
