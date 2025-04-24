<?php

declare(strict_types=1);

namespace Mitalteli\Webtrees;

use Fisharebest\Webtrees\Services\ModuleService;
use Mitalteli\Webtrees\Chart\EnhancedFamilyBookChart\EnhancedFamilyBookChartModule;

require __DIR__ . '/src/FamilyBookChartEnhancedModule.php';

$moduleService = EnhancedFamilyBookChartModule::getClass(ModuleService::class);
return new EnhancedFamilyBookChartModule($moduleService);   
