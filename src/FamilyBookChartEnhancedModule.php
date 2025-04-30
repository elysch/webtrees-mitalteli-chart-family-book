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
use Fisharebest\Localization\Translation;

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

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '1.4.0';
    public const GITHUB_REPO = 'webtrees-mitalteli-chart-family-book';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-chart-family-book/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';
    protected const ROUTE_URL = '/tree/{tree}/mitalteli-family-book-{book_size}-{generations}-{spouses}-{marriages}-{places_format}-{extra_images}/{xref}';

    // Limits
    protected const MINIMUM_BOOK_SIZE = 2;
    protected const MAXIMUM_BOOK_SIZE = 5;

    protected const MINIMUM_GENERATIONS = 2;
    protected const MAXIMUM_GENERATIONS = 10;

    // PLAC format
    // https://github.com/Neriderc/GVExport/blob/main/app/Settings.php#L50
    const OPTION_FULL_PLACE_NAME = 0;
    const OPTION_CITY_ONLY = 5;
    const OPTION_CITY_AND_COUNTRY = 10;
    const OPTION_2_LETTER_ISO = 20;
    const OPTION_3_LETTER_ISO = 30;

    public const OPTIONS_ABBR_PLACES = [self::OPTION_FULL_PLACE_NAME => "Full place name", self::OPTION_CITY_ONLY => "City only",  self::OPTION_CITY_AND_COUNTRY => "City and country",  self::OPTION_2_LETTER_ISO => "City and 2 letter ISO country code", self::OPTION_3_LETTER_ISO => "City and 3 letter ISO country code"];

    // Defaults
    public const    DEFAULT_GENERATIONS            = '3';
    public const    DEFAULT_DESCENDANT_GENERATIONS = '6';
    public const    DEFAULT_PLACES_FORMAT          = self::OPTION_FULL_PLACE_NAME;

     /**
      *
      * @param ModuleService $module_service
      */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    protected const DEFAULT_PARAMETERS             = [
        'book_size'     => self::DEFAULT_GENERATIONS,
        'generations'   => self::DEFAULT_DESCENDANT_GENERATIONS,
        'spouses'       => true,
        'marriages'     => true,
        'places_format' => self::DEFAULT_PLACES_FORMAT,
        'extra_images'  => true,
    ];

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

        ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
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
     * Returns an abbreviated version of the PLAC string.
     * Taken from https://github.com/Neriderc/GVExport/blob/f5737ad66e5a7669cd4bd50d9efffb9e7ee1aa83/app/Dot.php#L1519 and modified variable names, arguments and removed $settings
     *
     * @param	string $place_long Place string in long format (Town,County,State/Region,Country)
     * @return	string	The abbreviated place name
     */
    public static function getAbbreviatedPlace(string $place_long, int $place_format): string
    {
        // If chose no abbreviating, then return string untouched
        if ($place_format == self::OPTION_FULL_PLACE_NAME) {
            return $place_long;
        }

        $htmlBefore = '';
        $htmlAfter = '';
        if ( preg_match('@^(<[^>]+?>)(.*)(</[^>]+?>)$@', $place_long, $matches) ) {
            $htmlBefore = $matches[1];
            $place_long = $matches[2];
            $htmlAfter = $matches[3];
        }
        
        // Cut the place name up into pieces using the commas
        $place_chunks = explode(",", $place_long);
        $place = "";
        $chunk_count = count($place_chunks);
        $abbreviating_country = !($chunk_count == 1 && ($place_format == self::OPTION_2_LETTER_ISO || $place_format == self::OPTION_3_LETTER_ISO));

        // Add city to our return string
        if (!empty($place_chunks[0]) && $abbreviating_country) {
            $place .= trim($place_chunks[0]);

            if ($place_format == self::OPTION_CITY_ONLY) {
                return $htmlBefore . $place . $htmlAfter;
            }
        }

        // Chose to keep just the first and last sections
        if ($place_format == self::OPTION_CITY_AND_COUNTRY) {
            if (!empty($place_chunks[$chunk_count - 1]) && ($chunk_count > 1)) {
                if (!empty($place)) {
                    $place .= ", ";
                }
                $place .= trim($place_chunks[$chunk_count - 1]);
                return $htmlBefore . $place . $htmlAfter;
            }
        }

        /* Otherwise, we have chosen one of the ISO code options */
        switch ($place_format) {
            case self::OPTION_2_LETTER_ISO:
                $code = "iso2";
                break;
            case self::OPTION_3_LETTER_ISO:
                $code = "iso3";
                break;
            default:
                return $htmlBefore . $place_long . $htmlAfter;
        }

        /* It's possible the place name string was blank, meaning our return variable is
               still blank. We don't want to add a comma if that's the case. */
        if (!empty($place) && !empty($place_chunks[$chunk_count - 1]) && ($chunk_count > 1)) {
            $place .= ", ";
        }

        $countries = self::loadCountryDataFile($code);

        /* Look up our country in the array of country names.
           It must be an exact match, or it won't be abbreviated to the country code. */
        if (isset($countries[strip_tags(strtolower(trim($place_chunks[$chunk_count - 1])))])) {
            $place .= $countries[strip_tags(strtolower(trim($place_chunks[$chunk_count - 1])))];
        } else {
            // We didn't find country in the abbreviation list, so just add the full country name
            if (!empty($place_chunks[$chunk_count - 1])) {
                $place .= trim($place_chunks[$chunk_count - 1]);
            }
        }
        return $htmlBefore . $place . $htmlAfter;
    }

    /**
     * Loads country data from JSON file
     * https://github.com/Neriderc/GVExport/blob/main/app/Settings.php
     * Data comes from https://github.com/stefangabos/world_countries
     *
     * @param $type
     * @return array|false
     */
    private static function loadCountryDataFile($type) {
        switch ($type) {
            case 'iso2':
                $string = file_get_contents(dirname(__FILE__) . "/../resources/data/CountryRegionCodes2Char.json");
                break;
            case 'iso3':
                $string = file_get_contents(dirname(__FILE__) . "/../resources/data/CountryRegionCodes3Char.json");
                break;
            default:
                return false;
        }
        $json = json_decode($string, true);
        $countries = [];
        foreach ($json as $row => $value) {
            $countries[strtolower($row)] = strtoupper($value);
        }
        return $countries;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree               = Validator::attributes($request)->tree();
        $user               = Validator::attributes($request)->user();
        $xref               = Validator::attributes($request)->isXref()->string('xref');
        $book_size          = Validator::attributes($request)->isBetween(self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE)->integer('book_size');
        $generations        = Validator::attributes($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations');
        $spouses            = Validator::attributes($request)->boolean('spouses', false);
        $marriages          = Validator::attributes($request)->boolean('marriages', false);
        $places_format      = Validator::attributes($request)->isInArrayKeys(self::OPTIONS_ABBR_PLACES)->integer('places_format');
        $extra_images       = Validator::attributes($request)->boolean('extra_images', false);
        $hiddenprintcontent = Validator::parsedBody($request)->string('hiddenprintcontent', "");
        $paper_size         = Validator::parsedBody($request)->string('paper_size', "");
        $paper_orientation  = Validator::parsedBody($request)->string('paper_orientation', "");
        $ajax               = Validator::queryParams($request)->boolean('ajax', false);

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
                'tree'          => $tree->name(),
                'xref'          => Validator::parsedBody($request)->isXref()->string('xref'),
                'book_size'     => Validator::parsedBody($request)->isBetween(self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE)->integer('book_size'),
                'generations'   => Validator::parsedBody($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations'),
                'spouses'       => Validator::parsedBody($request)->boolean('spouses', false),
                'marriages'     => Validator::parsedBody($request)->boolean('marriages', false),
                'places_format' => Validator::parsedBody($request)->isInArrayKeys(self::OPTIONS_ABBR_PLACES)->integer('places_format'),
                'extra_images'  => Validator::parsedBody($request)->boolean('extra_images', false),
                'module_name'    => $this->name(),
                'module'        => $this,
            ]));
        }

        Auth::checkComponentAccess($this, ModuleChartInterface::class, $tree, $user);

        $individual  = Registry::individualFactory()->make($xref, $tree);
        $individual  = Auth::checkIndividualAccess($individual, false, true);

        if ($ajax) {
            $this->layout = 'layouts/ajax';

            return $this->viewResponse(
                $this->name() . '::modules/mitalteli-family-book-chart/chart', [
                    'individual'    => $individual,
                    'generations'   => $generations,
                    'book_size'     => $book_size,
                    'spouses'       => $spouses,
                    'marriages'     => $marriages,
                    'places_format' => $places_format,
                    'extra_images'  => $extra_images,
                    'module_name'   => $this->name(),
                    'module'        => $this,
                ]
            );
        }

        $ajax_url = $this->chartUrl($individual, [
            'ajax'          => true,
            'book_size'     => $book_size,
            'generations'   => $generations,
            'spouses'       => $spouses,
            'marriages'     => $marriages,
            'places_format' => $places_format,
            'extra_images'  => $extra_images,
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
            'module_name'         => $this->name(),
            'module'              => $this,
            'spouses'             => $spouses,
            'marriages'           => $marriages,
            'places_format'       => $places_format,
            'extra_images'        => $extra_images,
            'hiddenprintcontent'  => $hiddenprintcontent,
            'title'               => $this->chartTitle($individual),
            'tree'                => $tree,
        ]);
    }

    /**
     * Additional translations for module.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $file = $this->resourcesFolder() . 'lang/' . $language . '.php';

        return file_exists($file) ? (new Translation($file))->asArray() : [];
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
