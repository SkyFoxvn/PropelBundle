<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests;

use Exception;
use Propel\Bundle\PropelBundle\PropelBundle;
use Propel\Bundle\PropelBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;

class PropelBundleTest extends TestCase
{
    /**
     * @dataProvider importDatabaseLoaderScriptDataProvider
     *
     * @param bool $usesScript
     * @param string $loaderScriptDir
     * @param string $exceptionClass
     * @param bool $expectedReturn
     * @param string $message
     *
     * @return void
     */
    public function testImportDatabaseLoaderScript(
        bool $usesScript,
        string $loaderScriptDir,
        ?string $exceptionClass,
        ?bool $expectedReturn,
        string $message
        )
    {
        if($exceptionClass) {
            $this->expectException($exceptionClass);
        }
        $actualReturn = $this->runDatabaseLoaderScriptImport($usesScript, $loaderScriptDir);
        $this->assertSame($expectedReturn, $actualReturn, $message);
    }

    public static function importDatabaseLoaderScriptDataProvider(): array
    {
        return [
            // [<uses script>, <script dir>, <exception class>, <expected return>, <message>]
            [false, '', null, false, 'Should not import script if not set in configuration'],
            [true, '/this/dir/does/not/exists/bababui/bababui/', Exception::class, null, 'Should throw exception if scripts dir does not exist'],
            [true, __DIR__ . '/Fixtures', null, true, 'Should import script']
        ];
    }
    
    public function runDatabaseLoaderScriptImport(bool $usesScript, string $loaderScriptDir=''): bool
    {
        $bundle = new class() extends PropelBundle{
            public function runImport(): bool
            {
                return $this->importDatabaseLoaderScript();
            }
        };

        $config = [
            'paths' => [
                'loaderScriptDir' => $loaderScriptDir
            ]
        ];
        
        $container = new Container();
        $container->setParameter('propel.configuration', $config);
        $container->setParameter('propel.usesDatabaseLoaderScript', $usesScript);
        $bundle->setContainer($container);

        return $bundle->runImport();
    }
}
