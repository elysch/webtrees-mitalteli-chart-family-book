<?php

/**
 */

declare(strict_types=1);

namespace Mitalteli\Webtrees\Chart\EnhancedFamilyBookChart;

use \Datetime;

use Aura\Router\RouterContainer;
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
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Http\Exceptions\HttpNotFoundException;

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
    public const CUSTOM_VERSION = '2.0.6';
    public const GITHUB_REPO = 'webtrees-mitalteli-chart-family-book';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-chart-family-book/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

    /** Sorts the module next to the author's other modules in the module list.
     * The prefix is added in a single place, customTranslations(), to the two titles below. Every place that
     * translates them (title(), chartTitle(), the page header, the downloaded file names...) gets the prefix
     * from there, in every language (also when the language has no translation and the English text is used). */
    private const TITLE_PREFIX = "\u{26EE} "; # ⛮

    /** Name of the module / chart. msgid of the translation that customTranslations() prefixes. */
    public const CHART_TITLE = 'Enhanced family book';

    /** Title of a chart instance (%s is an individual’s name). msgid that customTranslations() prefixes. */
    public const CHART_TITLE_OF = 'Enhanced family book of %s';

    // webtrees 2.3 replaced Aura.Router with its own router (and dropped fig/http-message-util).
    protected const ROUTE_COLLECTION_CLASS = \Fisharebest\Webtrees\Http\Routing\RouteCollection::class;
    protected const HTTP_METHOD_POST       = 'POST';
    protected const ROUTE_URL = '/tree/{tree}/mitalteli-family-book-{book_size}-{generations}-{places_format}-{spouses}-{marriages}-{divorces}-{extra_images}-{debug}/{xref}';

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

    // Defaults
    public const    DEFAULT_GENERATIONS            = '3';
    public const    DEFAULT_DESCENDANT_GENERATIONS = '8';
    public const    DEFAULT_PLACES_FORMAT          = self::OPTION_FULL_PLACE_NAME;

    // DEBUG
    public const DEBUG_OPTION_NO_DEBUG = 0;
    public const DEBUG_OPTION_PLACE_SUBSTITUTION = 1;

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
        'places_format' => self::DEFAULT_PLACES_FORMAT,
        'spouses'       => true,
        'marriages'     => true,
        'divorces'      => true,
        'extra_images'  => true,
        'debug'         => self::DEBUG_OPTION_NO_DEBUG,
    ];

    /**
     * Initialization.
     *
     * @return void
     */
    public function boot(): void
    {
        if (class_exists(self::ROUTE_COLLECTION_CLASS)) {
            // webtrees 2.3+: the route name is the controller class, and the controller is
            // resolved from the container. Custom modules are not created by the container,
            // so register this (already named) instance; otherwise a fresh, unnamed one is made.
            Registry::container()->set(static::class, $this);
            // Our handle() method serves both GET and POST requests.
            Registry::routeFactory()->routeMap()->add(static::ROUTE_URL, static::class);
        } elseif (version_compare(Webtrees::VERSION, '2.1.0', '>=')) {
            Registry::routeFactory()->routeMap()
                ->get(static::class, static::ROUTE_URL, $this)
                ->allows(self::HTTP_METHOD_POST);
        } else {
            $router_container = app(RouterContainer::class);

            $router_container->getMap()
                ->get(static::class, static::ROUTE_URL, $this)
                ->allows(self::HTTP_METHOD_POST)
                ->tokens([
                    'book_size'     => '\d+',
                    'generations'   => '\d+',
                    'places_format' => '\d+',
                    'spouses'       => '1?',
                    'marriages'     => '1?',
                    'divorces'      => '1?',
                    'extra_images'  => '1?',
                    'debug'         => '\d+',
                ]);
        }

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
        /* I18N: Name of a module/chart. The sorting prefix is injected by customTranslations() */
        return I18N::translate(self::CHART_TITLE);
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
        /* I18N: %s is an individual’s name. The sorting prefix is injected by customTranslations() */
        return I18N::translate(self::CHART_TITLE_OF, $individual->fullName());
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
        $urlTmp = route(static::class, [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters + self::DEFAULT_PARAMETERS);

            #$this::debugMessage(/*$pPrefix*/ "def: ", /*$pMsgLog*/ self::DEFAULT_PARAMETERS, /*$pMsgStd*/ '', /*$pSuffix*/ '', /*$pToLog*/ true, /*$pToStd*/ false, /*$pToJsConsole*/ false, /*$pWithTime*/ false);
            #$this::debugMessage(/*$pPrefix*/ "parameters: ", /*$pMsgLog*/ $parameters, /*$pMsgStd*/ '', /*$pSuffix*/ '', /*$pToLog*/ true, /*$pToStd*/ false, /*$pToJsConsole*/ false, /*$pWithTime*/ false);
            #$this::debugMessage(/*$pPrefix*/ "urlTmp: $urlTmp", /*$pMsgLog*/ null, /*$pMsgStd*/ '', /*$pSuffix*/ '', /*$pToLog*/ true, /*$pToStd*/ false, /*$pToJsConsole*/ false, /*$pWithTime*/ false);

        return $urlTmp;
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

    public function abbrPlacesOptions() {
        return [
            self::OPTION_FULL_PLACE_NAME  => I18N::translate("Full place name"), 
            self::OPTION_CITY_ONLY        => I18N::translate("City only"),  
            self::OPTION_CITY_AND_COUNTRY => I18N::translate("City and country"),  
            self::OPTION_2_LETTER_ISO     => I18N::translate("City and 2 letter ISO country code"), 
            self::OPTION_3_LETTER_ISO     => I18N::translate("City and 3 letter ISO country code"),
        ];
    }

    public function debugOptions() {
        return [
            self::DEBUG_OPTION_NO_DEBUG           => I18N::translate("No debug"),
            self::DEBUG_OPTION_PLACE_SUBSTITUTION => I18N::translate("Place substitution debug"),
        ];
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // webtrees 2.0.x has an older Validator (no attributes(), boolean(), isXref(), isInArrayKeys()...),
        // so the parameters are read through readParameters(), which works with every supported version.
        $tree               = $this->requestTree($request);
        $user               = $this->requestUser($request);
        $params             = $this->readParameters($request, 'attributes');
        $xref               = $params['xref'];
        $book_size          = $params['book_size'];
        $generations        = $params['generations'];
        $spouses            = $params['spouses'];
        $marriages          = $params['marriages'];
        $divorces           = $params['divorces'];
        $places_format      = $params['places_format'];
        $extra_images       = $params['extra_images'];
        $debug              = $params['debug'];
        $hiddenprintcontent = $this->optionalString($request, 'parsedBody', 'hiddenprintcontent');
        $paper_size         = $this->optionalString($request, 'parsedBody', 'paper_size');
        $paper_orientation  = $this->optionalString($request, 'parsedBody', 'paper_orientation');
        $ajax               = $this->optionalBoolean($request, 'queryParams', 'ajax');

        #ini_set('log_errors_max_len','0');

        if (isset($hiddenprintcontent) and (strlen($hiddenprintcontent) > 0)) {
            $html_report_content=base64_decode($hiddenprintcontent);
            $html_report_content=html_entity_decode($html_report_content, ENT_QUOTES ,'UTF-8'); # TODO: check whether this decoding is needed or harmful

            require_once('FamilyBookChartEnhancedModuleGenPdf.php');

            $headers = ['content-type' => 'application/pdf'];

            // tecnickcom/tcpdf was replaced by tc-lib-pdf in webtrees 2.3, so this file may not exist.
            $pdf_file = 'vendor/tecnickcom/tcpdf/examples/example_012.pdf';

            if (!is_file($pdf_file)) {
                throw self::httpException('HttpNotFoundException');
            }

            $pdf = file_get_contents($pdf_file);

            // webtrees 2.3 uses an HttpStatusCode enum, earlier versions use an int.
            $status_ok = function_exists('enum_exists') && enum_exists(\Fisharebest\Webtrees\Enums\HttpStatusCode::class) ? \Fisharebest\Webtrees\Enums\HttpStatusCode::OK : 200;

            return response($pdf, $status_ok, $headers);
        }

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === self::HTTP_METHOD_POST) {
            $post = $this->readParameters($request, 'parsedBody');

            return redirect(route(static::class, [
                'tree'          => $tree->name(),
                'xref'          => $post['xref'],
                'book_size'     => $post['book_size'],
                'generations'   => $post['generations'],
                'spouses'       => $post['spouses'],
                'marriages'     => $post['marriages'],
                'divorces'      => $post['divorces'],
                'places_format' => $post['places_format'],
                'extra_images'  => $post['extra_images'],
                'debug'         => $post['debug'],
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
                    'divorces'      => $divorces,
                    'places_format' => $places_format,
                    'extra_images'  => $extra_images,
                    'module_name'   => $this->name(),
                    'module'        => $this,
                    'debug'         => $debug,
                ]
            );
        }

        $ajax_url = $this->chartUrl($individual, [
            'ajax'          => true,
            'book_size'     => $book_size,
            'generations'   => $generations,
            'spouses'       => $spouses,
            'marriages'     => $marriages,
            'divorces'      => $divorces,
            'places_format' => $places_format,
            'extra_images'  => $extra_images,
            'debug'         => $debug,
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
            'divorces'            => $divorces,
            'places_format'       => $places_format,
            'extra_images'        => $extra_images,
            'hiddenprintcontent'  => $hiddenprintcontent,
            'title'               => $this->chartTitle($individual),
            'tree'                => $tree,
            'debug'               => $debug,
        ]);
    }

    /**
     * HTTP exceptions moved from Fisharebest\Webtrees\Exceptions (2.0.x) to Fisharebest\Webtrees\Http\Exceptions (2.1+).
     *
     * @param string $name Short class name, e.g. HttpBadRequestException
     *
     * @return \Throwable
     */
    private static function httpException(string $name, string $message = ''): \Throwable
    {
        foreach (['Fisharebest\\Webtrees\\Http\\Exceptions\\', 'Fisharebest\\Webtrees\\Exceptions\\'] as $namespace) {
            $class = $namespace . $name;

            if (class_exists($class)) {
                return new $class($message);
            }
        }

        return new \RuntimeException($message);
    }

    /**
     * Does this webtrees version have the full Validator API this module uses (2.1+)?
     * webtrees 2.0.x does not, and calling it there ends in "Call to undefined method".
     */
    private static function hasModernValidator(): bool
    {
        foreach (['attributes', 'boolean', 'isXref', 'isInArrayKeys', 'tree', 'user'] as $method) {
            if (!method_exists(Validator::class, $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * New Validator for a source of request parameters (a fresh one each time: rules accumulate in the object).
     *
     * @param ServerRequestInterface $request
     * @param string                 $source 'attributes', 'parsedBody' or 'queryParams'
     */
    private static function validatorFor(ServerRequestInterface $request, string $source): Validator
    {
        switch ($source) {
            case 'attributes':
                return Validator::attributes($request);
            case 'parsedBody':
                return Validator::parsedBody($request);
            default:
                return Validator::queryParams($request);
        }
    }

    /**
     * Raw parameters of a source, for webtrees versions whose Validator is too old.
     *
     * @return array<mixed>
     */
    private static function rawParameters(ServerRequestInterface $request, string $source): array
    {
        switch ($source) {
            case 'attributes':
                return $request->getAttributes();
            case 'parsedBody':
                return (array) $request->getParsedBody();
            default:
                return $request->getQueryParams();
        }
    }

    /**
     * The tree of the request (a route attribute).
     */
    private function requestTree(ServerRequestInterface $request): \Fisharebest\Webtrees\Tree
    {
        if (self::hasModernValidator()) {
            return Validator::attributes($request)->tree();
        }

        $tree = $request->getAttribute('tree');

        if ($tree instanceof \Fisharebest\Webtrees\Tree) {
            return $tree;
        }

        throw self::httpException('HttpNotFoundException');
    }

    /**
     * The user of the request (a request attribute set by webtrees).
     */
    private function requestUser(ServerRequestInterface $request): \Fisharebest\Webtrees\Contracts\UserInterface
    {
        if (self::hasModernValidator()) {
            return Validator::attributes($request)->user();
        }

        $user = $request->getAttribute('user');

        return $user instanceof \Fisharebest\Webtrees\Contracts\UserInterface ? $user : Auth::user();
    }

    /**
     * Optional string parameter, '' when missing or not a string.
     */
    private function optionalString(ServerRequestInterface $request, string $source, string $name): string
    {
        if (self::hasModernValidator()) {
            return self::validatorFor($request, $source)->string($name, '');
        }

        $value = self::rawParameters($request, $source)[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Optional boolean parameter, false when missing or not recognised.
     */
    private function optionalBoolean(ServerRequestInterface $request, string $source, string $name): bool
    {
        if (self::hasModernValidator()) {
            return self::validatorFor($request, $source)->boolean($name, false);
        }

        return self::toBoolean(self::rawParameters($request, $source)[$name] ?? null);
    }

    /**
     * Strict conversion to boolean: only the usual "true" spellings give true.
     *
     * @param mixed $value
     */
    private static function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_string($value) && in_array(strtolower($value), ['1', 'on', 'true', 'yes'], true);
    }

    /**
     * Read and validate every parameter of the chart, from the route (attributes) or from the POST body.
     * With webtrees 2.1+ the native Validator does it. With 2.0.x (no such Validator methods) the same
     * checks are done here: anything outside the allowed values is rejected with a 400 error (never coerced).
     *
     * @param ServerRequestInterface $request
     * @param string                 $source 'attributes' or 'parsedBody'
     *
     * @return array{xref:string,book_size:int,generations:int,spouses:bool,marriages:bool,divorces:bool,places_format:int,extra_images:bool,debug:int}
     */
    private function readParameters(ServerRequestInterface $request, string $source): array
    {
        if (self::hasModernValidator()) {
            $v = function () use ($request, $source): Validator {
                return self::validatorFor($request, $source);
            };

            return [
                'xref'          => $v()->isXref()->string('xref'),
                'book_size'     => $v()->isBetween(self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE)->integer('book_size'),
                'generations'   => $v()->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations'),
                'spouses'       => $v()->boolean('spouses', false),
                'marriages'     => $v()->boolean('marriages', false),
                'divorces'      => $v()->boolean('divorces', false),
                'places_format' => $v()->isInArrayKeys($this->abbrPlacesOptions())->integer('places_format'),
                'extra_images'  => $v()->boolean('extra_images', false),
                'debug'         => $v()->isInArrayKeys($this->debugOptions())->integer('debug'),
            ];
        }

        $raw = self::rawParameters($request, $source);

        $xref = $raw['xref'] ?? null;

        if (!is_string($xref) || preg_match('/^' . \Fisharebest\Webtrees\Gedcom::REGEX_XREF . '$/D', $xref) !== 1) {
            throw self::httpException('HttpBadRequestException', 'xref');
        }

        return [
            'xref'          => $xref,
            'book_size'     => self::legacyInteger($raw, 'book_size', self::MINIMUM_BOOK_SIZE, self::MAXIMUM_BOOK_SIZE),
            'generations'   => self::legacyInteger($raw, 'generations', self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS),
            'spouses'       => self::toBoolean($raw['spouses'] ?? null),
            'marriages'     => self::toBoolean($raw['marriages'] ?? null),
            'divorces'      => self::toBoolean($raw['divorces'] ?? null),
            'places_format' => self::legacyInteger($raw, 'places_format', null, null, array_keys($this->abbrPlacesOptions())),
            'extra_images'  => self::toBoolean($raw['extra_images'] ?? null),
            'debug'         => self::legacyInteger($raw, 'debug', null, null, array_keys($this->debugOptions())),
        ];
    }

    /**
     * Integer parameter (only decimal digits), inside a range or among the allowed values.
     *
     * @param array<mixed>   $raw
     * @param int|null       $min
     * @param int|null       $max
     * @param array<int>|null $allowed
     */
    private static function legacyInteger(array $raw, string $name, ?int $min, ?int $max, ?array $allowed = null): int
    {
        $value = $raw[$name] ?? null;

        if (is_string($value) && ctype_digit($value) && strlen($value) <= 9) {
            $value = (int) $value;
        }

        if (!is_int($value)
            || ($min !== null && $value < $min)
            || ($max !== null && $value > $max)
            || ($allowed !== null && !in_array($value, $allowed, true))) {
            throw self::httpException('HttpBadRequestException', $name);
        }

        return $value;
    }

    public static function debugMessage($pPrefix, $pMsgLog, $pMsgStd, $pSuffix, $pToLog, $pToStd, $pToJsConsole, $pWithTime) {
        $timeStr = '';
        $msgLogStr = '';
        if ($pWithTime) {
            $t = microtime(true); $now = DateTime::createFromFormat('U.u', sprintf('%f', $t)); $now = $now->format("H:i:s.v");
            $timeStr = "-" . $now; usleep(1);
        }
        if (isset($pMsgLog)) $msgLogStr = "-" . print_r($pMsgLog, true);
        if (!empty($pMsgStd)) $pMsgStd = "-" . $pMsgStd;
        if (!empty($pSuffix)) $pSuffix = "-" . $pSuffix;
        if ($pToStd) {
            echo $pPrefix . $pMsgStd . $pSuffix . $timeStr;
        }
        if ($pToLog) {
            error_log($pPrefix . $msgLogStr . $pSuffix . $timeStr . 
                      " | " .  basename(dirname(debug_backtrace()[0]['file'])) . "/" . basename(debug_backtrace()[0]['file']) . 
                      ":" . debug_backtrace()[0]['line']);
        }
        if ($pToJsConsole) {
            echo "<script>console.log(" . json_encode( $pPrefix  . $pSuffix . $timeStr . 
                                      " | " . basename(dirname(debug_backtrace()[0]['file'])) . "/" . basename(debug_backtrace()[0]['file']) . 
                                      ":" . debug_backtrace()[0]['line'] ) . ");</script>";
        }
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
        $translations = [];

        // fisharebest/localization (Translation) was removed in webtrees 2.3.
        // Our language files are plain PHP files returning an array, so just include them.
        // $language is validated before it is used to build a file path.
        if (preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]+)*$/', $language) === 1) {
            $file = $this->resourcesFolder() . 'lang/' . $language . '.php';

            if (is_file($file)) {
                $included     = include $file;
                $translations = is_array($included) ? $included : [];
            }
        }

        // The sorting prefix is added here, once, to every title of the module (also when the language
        // has no translation of it and the English text is used, and also for languages without a file).
        // The prefix has no "%" so it cannot alter the sprintf() format of CHART_TITLE_OF.
        foreach ([self::CHART_TITLE, self::CHART_TITLE_OF] as $msgid) {
            $title                = $translations[$msgid] ?? $msgid;
            $translations[$msgid] = self::TITLE_PREFIX . (is_string($title) ? $title : $msgid);
        }

        return $translations;
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
