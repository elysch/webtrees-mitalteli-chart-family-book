<?php

/**
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\FanChart\RequestHandlers;
#namespace Fisharebest\Webtrees\Http\RequestHandlers;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\Exceptions\HttpGoneException;
use Fisharebest\Webtrees\Module\FamilyBookChartModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Redirect URLs created by webtrees 1.x (and PhpGedView).
 */
class RedirectFamilyBookPhp implements RequestHandlerInterface
{
    private ModuleService $module_service;

    private TreeService $tree_service;

    public function __construct(ModuleService $module_service, TreeService $tree_service)
    {
        $this->tree_service   = $tree_service;
        $this->module_service = $module_service;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
exit;
        $ged         = Validator::queryParams($request)->string('ged', Site::getPreference('DEFAULT_GEDCOM'));
        $root_id     = Validator::queryParams($request)->string('rootid', '');
        $generations = Validator::queryParams($request)->string('generations', FamilyBookChartModule::DEFAULT_GENERATIONS);
        $descent     = Validator::queryParams($request)->string('descent', FamilyBookChartModule::DEFAULT_DESCENDANT_GENERATIONS);
        $tree        = $this->tree_service->all()->get($ged);
        $module      = $this->module_service->findByInterface(FamilyBookChartModule::class)->first();

        if ($tree instanceof Tree && $module instanceof FamilyBookChartModule) {
            $individual = Registry::individualFactory()->make($root_id, $tree) ?? $tree->significantIndividual(Auth::user());

            $url = $module->chartUrl($individual, [
                'book_size'   => $generations,
                'generations' => $descent,
            ]);

            return Registry::responseFactory()
                ->redirectUrl($url, StatusCodeInterface::STATUS_MOVED_PERMANENTLY)
                ->withHeader('Link', '<' . $url . '>; rel="canonical"');
        }

        throw new HttpGoneException();
    }
}
