<?php

/**
 * Example report.
 */

declare(strict_types=1);

namespace Mitalteli\Webtrees;

use Composer\Autoload\ClassLoader;
use Mitalteli\Webtrees\Chart\EnhancedFamilyBookChart\EnhancedFamilyBookChartModule;

#// Register our namespace
#$loader = new ClassLoader();
#$loader->addPsr4('Mitalteli\\Webtrees\\Chart\\EnhancedFamilyBookChart\\', __DIR__ . '/src');
#$loader->register();

require __DIR__ . '/src/FamilyBookChartEnhancedModule.php';

return new EnhancedFamilyBookChartModule();
