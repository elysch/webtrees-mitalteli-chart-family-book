<?php

/**
 */

declare(strict_types=1);

namespace Mitalteli\Webtrees\Chart\EnhancedFamilyBookChart;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleChartInterface;
use Fisharebest\Webtrees\Module\ModuleChartTrait;

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Report\ReportParserGenerate;
use Fisharebest\Webtrees\Report\PdfRenderer;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Services\ModuleService;

use function route;
use function ob_get_clean;
use function ob_start;
use function response;
use finfo;


/**
 * Class EnhancedFamilyBookChartModule
 */
class EnhancedFamilyBookChartModule extends AbstractModule implements ModuleChartInterface, RequestHandlerInterface, ModuleCustomInterface 
{
    use ModuleCustomTrait;
    use ModuleChartTrait;
    public ModuleService $module_service;

     /**
      *
      * @param ModuleService $module_service
      */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

     /**
     * @var string
     */
    public const CUSTOM_AUTHOR = 'elysch';

    /**
     * @var string
     */
    public const CUSTOM_VERSION = '1.1.1';

     /**
     * @var string
     */
    public const GITHUB_REPO = 'webtrees-mitalteli-chart-family-book';

     /**
     * @var string
     */
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-chart-family-book/';

     /**
     * @var string
     */
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

     /**
     * @var string
     */
    protected const ROUTE_URL = '/tree/{tree}/mitalteli-family-book-{book_size}-{generations}-{spouses}-{marriages}-{full_places}-{extra_images}/{xref}';

    // Defaults
    public const    DEFAULT_GENERATIONS            = '3';
    public const    DEFAULT_DESCENDANT_GENERATIONS = '6';

    protected const DEFAULT_PARAMETERS             = [
        'book_size'    => self::DEFAULT_GENERATIONS,
        'generations'  => self::DEFAULT_DESCENDANT_GENERATIONS,
        'spouses'      => true,
        'marriages'    => true,
        'full_places'  => true,
        'extra_images' => true,
    ];

    // Limits
    protected const MINIMUM_BOOK_SIZE = 2;
    protected const MAXIMUM_BOOK_SIZE = 5;

    protected const MINIMUM_GENERATIONS = 2;
    protected const MAXIMUM_GENERATIONS = 10;

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        ini_set('max_execution_time', '300'); //300 seconds = 5 minutes ESL!!!
    }

     /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/../resources/';
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module/chart */
        return I18N::translate('Enhanced family book');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “EnhancedFamilyBookChart” module */
        return I18N::translate('A chart of an individual’s ancestors and descendants, as a family book (enhanced with marriages, images, and more).');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

     /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::AUTHOR_WEBSITE;
    }


    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        return "\n" . '<link rel="stylesheet" href="' . e($this->assetUrl('css/style.css')) . '">' . "\n";
    }

    /**
     * CSS class for the URL.
     *
     * @return string
     */
    public function chartMenuClass(): string
    {
        return 'menu-chart-enhancedfamilybook menu-chart-familybook';
    }

    /**
     * Return a menu item for this chart - for use in individual boxes.
     *
     * @param Individual $individual
     *
     * @return Menu|null
     */
    public function chartBoxMenu(Individual $individual): ?Menu
    {
        return $this->chartMenu($individual);
    }

    /**
     * The title for a specific instance of this chart.
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function chartTitle(Individual $individual): string
    {
        /* I18N: %s is an individual’s name */
        return I18N::translate('Enhanced family book of %s', $individual->fullName());
    }

    /**
     * The URL for a page showing chart options.
     *
     * @param Individual                                $individual
     * @param array<bool|int|string|array<string>|null> $parameters
     *
     * @return string
     */
    public function chartUrl(Individual $individual, array $parameters = []): string
    {
        return route(static::class, [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters + self::DEFAULT_PARAMETERS);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree         = Validator::attributes($request)->tree();
        $user         = Validator::attributes($request)->user();
        $xref         = Validator::attributes($request)->isXref()->string('xref');
        $book_size    = Validator::attributes($request)->isBetween(self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE)->integer('book_size');
        $generations  = Validator::attributes($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations');
        $spouses      = Validator::attributes($request)->boolean('spouses', false);
        $marriages    = Validator::attributes($request)->boolean('marriages', false); #ESL!!!
        $full_places  = Validator::attributes($request)->boolean('full_places', false); #ESL!!!
        $extra_images = Validator::attributes($request)->boolean('extra_images', false); #ESL!!!
        $hiddenprintcontent = Validator::parsedBody($request)->string('hiddenprintcontent', ""); #ESL!!!
        $paper_size = Validator::parsedBody($request)->string('paper_size', ""); #ESL!!!
        $paper_orientation = Validator::parsedBody($request)->string('paper_orientation', ""); #ESL!!!
        $ajax         = Validator::queryParams($request)->boolean('ajax', false);

        #ini_set('log_errors_max_len','0');

        if (isset($hiddenprintcontent) and (strlen($hiddenprintcontent) > 0)) {
            $html_report_content=base64_decode($hiddenprintcontent);
            $html_report_content=html_entity_decode($html_report_content, ENT_QUOTES ,'UTF-8'); # Revisar si hace falta o estorba

            require_once('FamilyBookChartEnhancedModuleGenPdf.php');

            $headers = ['content-type' => 'application/pdf'];

            $pdf = file_get_contents("vendor/tecnickcom/tcpdf/examples/example_012.pdf");

            return response($pdf, StatusCodeInterface::STATUS_OK, $headers);
        }

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            return redirect(route(static::class, [
                'tree'         => $tree->name(),
                'xref'         => Validator::parsedBody($request)->isXref()->string('xref'),
                'book_size'    => Validator::parsedBody($request)->isBetween(self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE)->integer('book_size'),
                'generations'  => Validator::parsedBody($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations'),
                'spouses'      => Validator::parsedBody($request)->boolean('spouses', false),
                'marriages'    => Validator::parsedBody($request)->boolean('marriages', false), #ESL!!!
                'full_places'  => Validator::parsedBody($request)->boolean('full_places', false), #ESL!!!
                'extra_images' => Validator::parsedBody($request)->boolean('extra_images', false), #ESL!!!
            ]));
        }

        Auth::checkComponentAccess($this, ModuleChartInterface::class, $tree, $user);

        $individual  = Registry::individualFactory()->make($xref, $tree);
        $individual  = Auth::checkIndividualAccess($individual, false, true);

        if ($ajax) {
            $this->layout = 'layouts/ajax';

        return $this->viewResponse(
            $this->name() . '::modules/mitalteli-family-book-chart/chart', [
                'individual'   => $individual,
                'generations'  => $generations,
                'book_size'    => $book_size,
                'spouses'      => $spouses,
                'marriages'    => $marriages,
                'full_places'  => $full_places,
                'extra_images' => $extra_images,
                'module'       => $this->name(),
            ]);
        }

        $ajax_url = $this->chartUrl($individual, [
            'ajax'         => true,
            'book_size'    => $book_size,
            'generations'  => $generations,
            'spouses'      => $spouses,
            'marriages'    => $marriages,
            'full_places'  => $full_places,
            'extra_images' => $extra_images,
        ]);

        return $this->viewResponse(
            $this->name() . '::modules/mitalteli-family-book-chart/page', [

            'ajax_url'            => $ajax_url,
            'book_size'           => $book_size,
            'generations'         => $generations,
            'individual'          => $individual,
            'maximum_book_size'   => self::MAXIMUM_BOOK_SIZE,
            'minimum_book_size'   => self::MINIMUM_BOOK_SIZE,
            'maximum_generations' => self::MAXIMUM_GENERATIONS,
            'minimum_generations' => self::MINIMUM_GENERATIONS,
            'module'              => $this->name(),
            'spouses'             => $spouses,
            'marriages'           => $marriages,
            'full_places'         => $full_places,
            'extra_images'        => $extra_images,
            'hiddenprintcontent'  => $hiddenprintcontent,
            'title'               => $this->chartTitle($individual),
            'tree'                => $tree,
        ]);
    }

    /**
     * A breaking change in webtrees 2.2.0 changes how the classes are retrieved.
     * This function allows support for both 2.1.X and 2.2.X versions
     * @param $class
     * @return mixed
     */
    static function getClass($class)
    {
        if (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
            return Registry::container()->get($class);
        } else {
            return app($class);
        }
    }
}
