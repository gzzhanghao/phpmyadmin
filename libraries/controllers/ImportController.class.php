<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Library that provides common import functions that are used by import plugins
 *
 * @package PhpMyAdmin-Import
 */

namespace PMA\Controllers;

use Exception;
use ImportPlugin;
use PMA_Console;
use PMA_Message;
use PMA_Util;
use SqlParser;

/**
 * Get the variables sent or posted to this script and a core script
 */
require_once 'libraries/common.inc.php';
require_once 'libraries/sql.lib.php';
require_once 'libraries/bookmark.lib.php';
require_once 'libraries/Console.class.php';
require_once 'libraries/check_user_privileges.lib.php';
require_once 'libraries/bookmark.lib.php';
require_once "libraries/plugin_interface.lib.php";
require_once 'libraries/zip_extension.lib.php';
require_once 'libraries/controllers/Controller.class.php';

/**
 * We do this check, DROP DATABASE does not need to be confirmed elsewhere
 */
define('PMA_CHK_DROP', 1);

class ImportController extends Controller
{
    /**
     * Constants definitions
     */

    /* MySQL type defs */
    const NONE = 0;
    const VARCHAR = 1;
    const INT = 2;
    const DECIMAL = 3;
    const BIGINT = 4;
    const GEOMETRY = 5;

    /* Decimal size defs */
    const M = 0;
    const D = 1;
    const FULL = 2;

    /* Table array defs */
    const TBL_NAME = 0;
    const COL_NAMES = 1;
    const ROWS = 2;

    /* Analysis array defs */
    const TYPES = 0;
    const SIZES = 1;
    const FORMATTEDSQL = 2;

    public $error;
    public $timeout_passed;

    /* Needed to quell the beast that is PMA_Message */
    protected $import_notice = null;
    protected $maximum_time;
    protected $timestamp;
    protected $read_multiply;
    protected $import_run_buffer;
    protected $skip_queries;
    protected $sql_query;
    protected $import_handle;
    protected $file_to_unlink;
    protected $compression;
    protected $charset_conversion;
    protected $charset_of_file;
    protected $format;
    protected $import_type;
    protected $go_sql;
    protected $complete_query;
    protected $display_query;
    protected $my_die;
    protected $reload;
    protected $last_query_with_results;
    protected $result;
    protected $msg;
    protected $executed_queries;
    protected $max_sql_len;
    protected $cfg;
    protected $sql_query_disabled;
    protected $db;
    protected $run_query;
    protected $is_superuser;
    protected $finished;
    protected $offset;
    protected $reset_charset;
    protected $bookmark_created;
    protected $size;
    protected $collation_connection;
    protected $import_text;
    protected $special_message;
    protected $analyzed_sql_results;
    protected $pmaThemeImage;
    protected $table;
    protected $is_js_confirmed;
    protected $MAX_FILE_SIZE;
    protected $message_to_show;
    protected $noplugin;
    protected $local_import_file;

    function __construct(
        $import_notice, $maximum_time, $timeout_passed, $timestamp,
        $read_multiply, $import_run_buffer, $skip_queries, $sql_query,
        $import_handle, $file_to_unlink, $compression, $charset_conversion,
        $charset_of_file, $format, $import_type, $go_sql,
        $complete_query, $display_query, $my_die, $error, $reload,
        $last_query_with_results, $result, $msg, $executed_queries,
        $max_sql_len, $cfg, $sql_query_disabled, $db, $run_query,
        $is_superuser, $finished, $offset, $reset_charset, $bookmark_created,
        $size, $collation_connection, $import_text, $special_message,
        $analyzed_sql_results, $pmaThemeImage, $table
    ) {
        parent::__construct();
        $this->import_notice = $import_notice;
        $this->maximum_time = $maximum_time;
        $this->timeout_passed = $timeout_passed;
        $this->timestamp = $timestamp;
        $this->read_multiply = $read_multiply;
        $this->import_run_buffer = $import_run_buffer;
        $this->skip_queries = $skip_queries;
        $this->sql_query = $sql_query;
        $this->import_handle = $import_handle;
        $this->file_to_unlink = $file_to_unlink;
        $this->compression = $compression;
        $this->charset_conversion = $charset_conversion;
        $this->charset_of_file = $charset_of_file;
        $this->format = $format;
        $this->import_type = $import_type;
        $this->go_sql = $go_sql;
        $this->complete_query = $complete_query;
        $this->display_query = $display_query;
        $this->my_die = $my_die;
        $this->error = $error;
        $this->reload = $reload;
        $this->last_query_with_results = $last_query_with_results;
        $this->result = $result;
        $this->msg = $msg;
        $this->executed_queries = $executed_queries;
        $this->max_sql_len = $max_sql_len;
        $this->cfg = $cfg;
        $this->sql_query_disabled = $sql_query_disabled;
        $this->db = $db;
        $this->run_query = $run_query;
        $this->is_superuser = $is_superuser;
        $this->finished = $finished;
        $this->offset = $offset;
        $this->reset_charset = $reset_charset;
        $this->bookmark_created = $bookmark_created;
        $this->size = $size;
        $this->collation_connection = $collation_connection;
        $this->import_text = $import_text;
        $this->special_message = $special_message;
        $this->analyzed_sql_results = $analyzed_sql_results;
        $this->pmaThemeImage = $pmaThemeImage;
        $this->table = $table;
    }

    function indexAction()
    {
        if (isset($_REQUEST['show_as_php'])) {
            $GLOBALS['show_as_php'] = $_REQUEST['show_as_php'];
        }

        // If there is a request to 'Simulate DML'.
        if (isset($_REQUEST['simulate_dml'])) {
            $this->handleSimulateDMLRequest();
            return;
        }

        // If it's a refresh console bookmarks request
        if (isset($_REQUEST['console_bookmark_refresh'])) {
            $response = $this->response;
            $response->addJSON(
                'console_message_bookmark', PMA_Console::getBookmarkContent()
            );
            return;
        }
        // If it's a console bookmark add request
        if (isset($_REQUEST['console_bookmark_add'])) {
            $response = $this->response;
            if (isset($_REQUEST['label']) && isset($_REQUEST['db'])
                && isset($_REQUEST['bookmark_query']) && isset($_REQUEST['shared'])
            ) {
                $cfgBookmark = PMA_Bookmark_getParams();
                $bookmarkFields = array(
                    'bkm_database' => $_REQUEST['db'],
                    'bkm_user'  => $cfgBookmark['user'],
                    'bkm_sql_query' => urlencode($_REQUEST['bookmark_query']),
                    'bkm_label' => $_REQUEST['label']
                );
                $isShared = ($_REQUEST['shared'] == 'true' ? true : false);
                if (PMA_Bookmark_save($bookmarkFields, $isShared)) {
                    $response->addJSON('message', __('Succeeded'));
                    $response->addJSON('data', $bookmarkFields);
                    $response->addJSON('isShared', $isShared);
                } else {
                    $response->addJSON('message', __('Failed'));
                }
                return;
            } else {
                $response->addJSON('message', __('Incomplete params'));
                return;
            }
        }

        /**
         * Sets globals from $_POST
         */
        if (isset($_POST['charset_of_file'])) {
            $this->charset_of_file = $GLOBALS['charset_of_file'] = $_POST['charset_of_file'];
        }
        if (isset($_POST['format'])) {
            $this->format = $GLOBALS['format'] = $_POST['format'];
        }
        if (isset($_POST['import_type'])) {
            $this->import_type = $GLOBALS['import_type'] = $_POST['import_type'];
        }
        if (isset($_POST['is_js_confirmed'])) {
            $this->is_js_confirmed = $GLOBALS['is_js_confirmed'] = $_POST['is_js_confirmed'];
        }
        if (isset($_POST['MAX_FILE_SIZE'])) {
            $this->MAX_FILE_SIZE = $GLOBALS['MAX_FILE_SIZE'] = $_POST['MAX_FILE_SIZE'];
        }
        if (isset($_POST['message_to_show'])) {
            $this->message_to_show = $GLOBALS['message_to_show'] = $_POST['message_to_show'];
        }
        if (isset($_POST['noplugin'])) {
            $this->noplugin = $GLOBALS['noplugin'] = $_POST['noplugin'];
        }
        if (isset($_POST['skip_queries'])) {
            $this->skip_queries = $GLOBALS['skip_queries'] = $_POST['skip_queries'];
        }
        if (isset($_POST['local_import_file'])) {
            $this->local_import_file = $GLOBALS['local_import_file'] = $_POST['local_import_file'];
        }

        // reset import messages for ajax request
        $_SESSION['Import_message']['message'] = null;
        $_SESSION['Import_message']['go_back_url'] = null;
        // default values
        $GLOBALS['reload'] = false;

        // Use to identify current cycle is executing
        // a multiquery statement or stored routine
        if (!isset($_SESSION['is_multi_query'])) {
            $_SESSION['is_multi_query'] = false;
        }

        $ajax_reload = array();
        // Are we just executing plain query or sql file?
        // (eg. non import, but query box/window run)
        if (! empty($this->sql_query)) {

            // apply values for parameters
            if (! empty($_REQUEST['parameterized'])) {
                $parameters = $_REQUEST['parameters'];
                foreach ($parameters as $parameter => $replacement) {
                    $quoted = preg_quote($parameter);
                    // making sure that :param does not apply values to :param1
                    $this->sql_query = preg_replace(
                        '/' . $quoted . '([^a-zA-Z0-9_])/',
                        PMA_Util::sqlAddSlashes($replacement) . '${1}',
                        $this->sql_query
                    );
                    // for parameters the appear at the end of the string
                    $this->sql_query = preg_replace(
                        '/' . $quoted . '$/',
                        PMA_Util::sqlAddSlashes($replacement),
                        $this->sql_query
                    );
                }
            }

            // run SQL query
            $import_text = $this->sql_query;
            $this->import_type = 'query';
            $this->format = 'sql';
            $_SESSION['sql_from_query_box'] = true;

            // If there is a request to ROLLBACK when finished.
            if (isset($_REQUEST['rollback_query'])) {
                $this->handleRollbackRequest($import_text);
            }

            // refresh navigation and main panels
            if (preg_match('/^(DROP)\s+(VIEW|TABLE|DATABASE|SCHEMA)\s+/i', $this->sql_query)) {
                $GLOBALS['reload'] = true;
                $ajax_reload['reload'] = true;
            }

            // refresh navigation panel only
            if (preg_match(
                '/^(CREATE|ALTER)\s+(VIEW|TABLE|DATABASE|SCHEMA)\s+/i',
                $this->sql_query
            )) {
                $ajax_reload['reload'] = true;
            }

            // do a dynamic reload if table is RENAMED
            // (by sending the instruction to the AJAX response handler)
            if (preg_match(
                '/^RENAME\s+TABLE\s+(.*?)\s+TO\s+(.*?)($|;|\s)/i',
                $this->sql_query,
                $rename_table_names
            )) {
                $ajax_reload['reload'] = true;
                $ajax_reload['table_name'] = PMA_Util::unQuote($rename_table_names[2]);
            }

            $this->sql_query = '';
        } elseif (! empty($sql_file)) {
            // run uploaded SQL file
            $import_file = $sql_file;
            $this->import_type = 'queryfile';
            $this->format = 'sql';
            unset($sql_file);
        } elseif (! empty($_REQUEST['id_bookmark'])) {
            // run bookmark
            $this->import_type = 'query';
            $this->format = 'sql';
        }

        // If we didn't get any parameters, either user called this directly, or
        // upload limit has been reached, let's assume the second possibility.
        if ($_POST == array() && $_GET == array()) {
            $message = PMA_Message::error(
                __(
                    'You probably tried to upload a file that is too large. Please refer ' .
                    'to %sdocumentation%s for a workaround for this limit.'
                )
            );
            $message->addParam('[doc@faq1-16]');
            $message->addParam('[/doc]');

            // so we can obtain the message
            $_SESSION['Import_message']['message'] = $message->getDisplay();
            $_SESSION['Import_message']['go_back_url'] = $GLOBALS['goto'];

            $message->display();
            return; // the footer is displayed automatically
        }

        // Add console message id to response output
        if (isset($_POST['console_message_id'])) {
            $response = $this->response;
            $response->addJSON('console_message_id', $_POST['console_message_id']);
        }

        /**
         * Sets globals from $_POST patterns, for import plugins
         * We only need to load the selected plugin
         */

        if (! in_array(
            $this->format,
            array(
                'csv',
                'ldi',
                'mediawiki',
                'ods',
                'shp',
                'sql',
                'xml'
            )
        )) {
            // this should not happen for a normal user
            // but only during an attack
            PMA_fatalError('Incorrect format parameter');
        }

        $post_patterns = array(
            '/^force_file_/',
            '/^' . $this->format . '_/'
        );

        PMA_setPostAsGlobal($post_patterns);

        // Check needed parameters
        PMA_Util::checkParameters(array('import_type', 'format'));

        // We don't want anything special in format
        $this->format = PMA_securePath($this->format);

        // Create error and goto url
        if ($this->import_type == 'table') {
            $err_url = 'tbl_import.php' . PMA_URL_getCommon(
                    array(
                        'db' => $this->db, 'table' => $this->table
                    )
                );
            $_SESSION['Import_message']['go_back_url'] = $err_url;
            $goto = 'tbl_import.php';
        } elseif ($this->import_type == 'database') {
            $err_url = 'db_import.php' . PMA_URL_getCommon(array('db' => $this->db));
            $_SESSION['Import_message']['go_back_url'] = $err_url;
            $goto = 'db_import.php';
        } elseif ($this->import_type == 'server') {
            $err_url = 'server_import.php' . PMA_URL_getCommon();
            $_SESSION['Import_message']['go_back_url'] = $err_url;
            $goto = 'server_import.php';
        } else {
            if (empty($goto) || !preg_match('@^(server|db|tbl)(_[a-z]*)*\.php$@i', $goto)) {
                if (/*overload*/mb_strlen($this->table) && /*overload*/mb_strlen($this->db)) {
                    $goto = 'tbl_structure.php';
                } elseif (/*overload*/mb_strlen($this->db)) {
                    $goto = 'db_structure.php';
                } else {
                    $goto = 'server_sql.php';
                }
            }
            if (/*overload*/mb_strlen($this->table) && /*overload*/mb_strlen($this->db)) {
                $common = PMA_URL_getCommon(array('db' => $this->db, 'table' => $this->table));
            } elseif (/*overload*/mb_strlen($this->db)) {
                $common = PMA_URL_getCommon(array('db' => $this->db));
            } else {
                $common = PMA_URL_getCommon();
            }
            $err_url  = $goto . $common
                . (preg_match('@^tbl_[a-z]*\.php$@', $goto)
                    ? '&amp;table=' . htmlspecialchars($this->table)
                    : '');
            $_SESSION['Import_message']['go_back_url'] = $err_url;
        }
        // Avoid setting selflink to 'import.php'
        // problem similar to bug 4276
        if (basename($_SERVER['SCRIPT_NAME']) === 'import.php') {
            $_SERVER['SCRIPT_NAME'] = $goto;
        }


        if (/*overload*/mb_strlen($this->db)) {
            $this->dbi->selectDb($this->db);
        }

        @set_time_limit($this->cfg['ExecTimeLimit']);
        if (! empty($this->cfg['MemoryLimit'])) {
            @ini_set('memory_limit', $this->cfg['MemoryLimit']);
        }

        $this->timestamp = time();
        if (isset($_REQUEST['allow_interrupt'])) {
            $this->maximum_time = ini_get('max_execution_time');
        } else {
            $this->maximum_time = 0;
        }

        // set default values
        $this->timeout_passed = false;
        $this->error = false;
        $this->read_multiply = 1;
        $this->finished = false;
        $this->offset = 0;
        $this->max_sql_len = 0;
        $this->file_to_unlink = '';
        $this->sql_query = '';
        $this->sql_query_disabled = false;
        $this->go_sql = false;
        $this->executed_queries = 0;
        $this->run_query = true;
        $this->charset_conversion = false;
        $this->reset_charset = false;
        $this->bookmark_created = false;

        // Bookmark Support: get a query back from bookmark if required
        if (! empty($_REQUEST['id_bookmark'])) {
            $id_bookmark = (int)$_REQUEST['id_bookmark'];
            switch ($_REQUEST['action_bookmark']) {
                case 0: // bookmarked query that have to be run
                    $import_text = PMA_Bookmark_get(
                        $this->db,
                        $id_bookmark,
                        'id',
                        isset($_REQUEST['action_bookmark_all'])
                    );
                    if (! empty($_REQUEST['bookmark_variable'])) {
                        $import_text = PMA_Bookmark_applyVariables(
                            $import_text
                        );
                    }

                    // refresh navigation and main panels
                    if (preg_match(
                        '/^(DROP)\s+(VIEW|TABLE|DATABASE|SCHEMA)\s+/i',
                        $import_text
                    )) {
                        $GLOBALS['reload'] = true;
                        $ajax_reload['reload'] = true;
                    }

                    // refresh navigation panel only
                    if (preg_match(
                        '/^(CREATE|ALTER)\s+(VIEW|TABLE|DATABASE|SCHEMA)\s+/i',
                        $import_text
                    )
                    ) {
                        $ajax_reload['reload'] = true;
                    }
                    break;
                case 1: // bookmarked query that have to be displayed
                    $import_text = PMA_Bookmark_get($this->db, $id_bookmark);
                    if ($GLOBALS['is_ajax_request'] == true) {
                        $message = PMA_Message::success(__('Showing bookmark'));
                        $response = $this->response;
                        $response->isSuccess($message->isSuccess());
                        $response->addJSON('message', $message);
                        $response->addJSON('sql_query', $import_text);
                        $response->addJSON('action_bookmark', $_REQUEST['action_bookmark']);
                        return;
                    } else {
                        $this->run_query = false;
                    }
                    break;
                case 2: // bookmarked query that have to be deleted
                    $import_text = PMA_Bookmark_get($this->db, $id_bookmark);
                    PMA_Bookmark_delete($id_bookmark);
                    if ($GLOBALS['is_ajax_request'] == true) {
                        $message = PMA_Message::success(__('The bookmark has been deleted.'));
                        $response = $this->response;
                        $response->isSuccess($message->isSuccess());
                        $response->addJSON('message', $message);
                        $response->addJSON('action_bookmark', $_REQUEST['action_bookmark']);
                        $response->addJSON('id_bookmark', $id_bookmark);
                        return;
                    } else {
                        $this->run_query = false;
                        $this->error = true; // this is kind of hack to skip processing the query
                    }
                    break;
            }
        } // end bookmarks reading

        // Do no run query if we show PHP code
        if (isset($GLOBALS['show_as_php'])) {
            $this->run_query = false;
            $this->go_sql = true;
        }

        // We can not read all at once, otherwise we can run out of memory
        $memory_limit = trim(@ini_get('memory_limit'));
        // 2 MB as default
        if (empty($memory_limit)) {
            $memory_limit = 2 * 1024 * 1024;
        }
        // In case no memory limit we work on 10MB chunks
        if ($memory_limit == -1) {
            $memory_limit = 10 * 1024 * 1024;
        }

        // Calculate value of the limit
        $memoryUnit = /*overload*/mb_strtolower(substr($memory_limit, -1));
        if ('m' == $memoryUnit) {
            $memory_limit = (int)substr($memory_limit, 0, -1) * 1024 * 1024;
        } elseif ('k' == $memoryUnit) {
            $memory_limit = (int)substr($memory_limit, 0, -1) * 1024;
        } elseif ('g' == $memoryUnit) {
            $memory_limit = (int)substr($memory_limit, 0, -1) * 1024 * 1024 * 1024;
        } else {
            $memory_limit = (int)$memory_limit;
        }

        // Just to be sure, there might be lot of memory needed for uncompression
        $read_limit = $memory_limit / 8;

        // handle filenames
        if (isset($_FILES['import_file'])) {
            $import_file = $_FILES['import_file']['tmp_name'];
        }
        if (! empty($this->local_import_file) && ! empty($this->cfg['UploadDir'])) {

            // sanitize $this->local_import_file as it comes from a POST
            $this->local_import_file = PMA_securePath($this->local_import_file);

            $import_file = PMA_Util::userDir($this->cfg['UploadDir'])
                . $this->local_import_file;

        } elseif (empty($import_file) || ! is_uploaded_file($import_file)) {
            $import_file  = 'none';
        }

        // Do we have file to import?

        if ($import_file != 'none' && ! $this->error) {
            // work around open_basedir and other limitations
            $open_basedir = @ini_get('open_basedir');

            // If we are on a server with open_basedir, we must move the file
            // before opening it.

            if (! empty($open_basedir)) {
                $tmp_subdir = ini_get('upload_tmp_dir');
                if (empty($tmp_subdir)) {
                    $tmp_subdir = sys_get_temp_dir();
                }
                $tmp_subdir = rtrim($tmp_subdir, DIRECTORY_SEPARATOR);
                if (is_writable($tmp_subdir)) {
                    $import_file_new = $tmp_subdir . DIRECTORY_SEPARATOR
                        . basename($import_file) . uniqid();
                    if (move_uploaded_file($import_file, $import_file_new)) {
                        $import_file = $import_file_new;
                        $this->file_to_unlink = $import_file_new;
                    }

                    $this->size = filesize($import_file);
                } else {

                    // If the php.ini is misconfigured (eg. there is no /tmp access defined
                    // with open_basedir), $tmp_subdir won't be writable and the user gets
                    // a 'File could not be read!' error (at $this->detectCompression), which
                    // is not too meaningful. Show a meaningful error message to the user
                    // instead.

                    $message = PMA_Message::error(
                        __(
                            'Uploaded file cannot be moved, because the server has ' .
                            'open_basedir enabled without access to the %s directory ' .
                            '(for temporary files).'
                        )
                    );
                    $message->addParam($tmp_subdir);
                    $this->stopImport($message);
                }
            }

            /**
             *  Handle file compression
             * @todo duplicate code exists in File.class.php
             */
            $this->compression = $this->detectCompression($import_file);
            if ($this->compression === false) {
                $message = PMA_Message::error(__('File could not be read!'));
                $this->stopImport($message); //Contains an 'exit'
            }

            switch ($this->compression) {
                case 'application/bzip2':
                    if ($this->cfg['BZipDump'] && @function_exists('bzopen')) {
                        $this->import_handle = @bzopen($import_file, 'r');
                    } else {
                        $message = PMA_Message::error(
                            __(
                                'You attempted to load file with unsupported compression ' .
                                '(%s). Either support for it is not implemented or disabled ' .
                                'by your configuration.'
                            )
                        );
                        $message->addParam($this->compression);
                        $this->stopImport($message);
                    }
                    break;
                case 'application/gzip':
                    if ($this->cfg['GZipDump'] && @function_exists('gzopen')) {
                        $this->import_handle = @gzopen($import_file, 'r');
                    } else {
                        $message = PMA_Message::error(
                            __(
                                'You attempted to load file with unsupported compression ' .
                                '(%s). Either support for it is not implemented or disabled ' .
                                'by your configuration.'
                            )
                        );
                        $message->addParam($this->compression);
                        $this->stopImport($message);
                    }
                    break;
                case 'application/zip':
                    if ($this->cfg['ZipDump'] && @function_exists('zip_open')) {
                        $zipResult = PMA_getZipContents($import_file);
                        if (! empty($zipResult['error'])) {
                            $message = PMA_Message::rawError($zipResult['error']);
                            $this->stopImport($message);
                        } else {
                            $import_text = $zipResult['data'];
                        }
                    } else {
                        $message = PMA_Message::error(
                            __(
                                'You attempted to load file with unsupported compression ' .
                                '(%s). Either support for it is not implemented or disabled ' .
                                'by your configuration.'
                            )
                        );
                        $message->addParam($this->compression);
                        $this->stopImport($message);
                    }
                    break;
                case 'none':
                    $this->import_handle = @fopen($import_file, 'r');
                    break;
                default:
                    $message = PMA_Message::error(
                        __(
                            'You attempted to load file with unsupported compression (%s). ' .
                            'Either support for it is not implemented or disabled by your ' .
                            'configuration.'
                        )
                    );
                    $message->addParam($this->compression);
                    $this->stopImport($message);
                    break;
            }
            // use isset() because zip compression type does not use a handle
            if (! $this->error && isset($this->import_handle) && $this->import_handle === false) {
                $message = PMA_Message::error(__('File could not be read!'));
                $this->stopImport($message);
            }
        } elseif (! $this->error) {
            if (! isset($import_text) || empty($import_text)) {
                $message = PMA_Message::error(
                    __(
                        'No data was received to import. Either no file name was ' .
                        'submitted, or the file size exceeded the maximum size permitted ' .
                        'by your PHP configuration. See [doc@faq1-16]FAQ 1.16[/doc].'
                    )
                );
                $this->stopImport($message);
            }
        }

        // so we can obtain the message
        //$_SESSION['Import_message'] = $message->getDisplay();

        // Convert the file's charset if necessary
        if ($GLOBALS['PMA_recoding_engine'] != PMA_CHARSET_NONE && isset($this->charset_of_file)) {
            if ($this->charset_of_file != 'utf-8') {
                $this->charset_conversion = true;
            }
        } elseif (isset($this->charset_of_file) && $this->charset_of_file != 'utf-8') {
            if (PMA_DRIZZLE) {
                // Drizzle doesn't support other character sets,
                // so we can't fallback to SET NAMES - throw an error
                $message = PMA_Message::error(
                    __(
                        'Cannot convert file\'s character'
                        . ' set without character set conversion library!'
                    )
                );
                $this->stopImport($message);
            } else {
                $this->dbi->query('SET NAMES \'' . $this->charset_of_file . '\'');
                // We can not show query in this case, it is in different charset
                $this->sql_query_disabled = true;
                $this->reset_charset = true;
            }
        }

        // Something to skip? (because timeout has passed)
        if (! $this->error && isset($_POST['skip'])) {
            $original_skip = $skip = $_POST['skip'];
            while ($skip > 0) {
                $this->importGetNextChunk($skip < $read_limit ? $skip : $read_limit);
                // Disable read progressivity, otherwise we eat all memory!
                $this->read_multiply = 1;
                $skip -= $read_limit;
            }
            unset($skip);
        }

        // This array contain the data like numberof valid sql queries in the statement
        // and complete valid sql statement (which affected for rows)
        $sql_data = array('valid_sql' => array(), 'valid_queries' => 0);

        if (! $this->error) {
            // Check for file existence
            /* @var $import_plugin ImportPlugin */
            $import_plugin = PMA_getPlugin(
                "import",
                $this->format,
                'libraries/plugins/import/',
                $this->import_type
            );
            if ($import_plugin == null) {
                $message = PMA_Message::error(
                    __('Could not load import plugins, please check your installation!')
                );
                $this->stopImport($message);
            } else {
                // Do the real import
                $default_fk_check = PMA_Util::handleDisableFKCheckInit();
                try {
                    $import_plugin->doImport($sql_data, $this);
                    PMA_Util::handleDisableFKCheckCleanup($default_fk_check);
                } catch (Exception $e) {
                    PMA_Util::handleDisableFKCheckCleanup($default_fk_check);
                    throw $e;
                }
            }
        }

        if (! empty($this->import_handle)) {
            fclose($this->import_handle);
        }

        // Cleanup temporary file
        if ($this->file_to_unlink != '') {
            unlink($this->file_to_unlink);
        }

        // Reset charset back, if we did some changes
        if ($this->reset_charset) {
            $this->dbi->query('SET CHARACTER SET utf8');
            $this->dbi->query(
                'SET SESSION collation_connection =\'' . $this->collation_connection . '\''
            );
        }

        // Show correct message
        if (! empty($id_bookmark) && $_REQUEST['action_bookmark'] == 2) {
            $message = PMA_Message::success(__('The bookmark has been deleted.'));
            $this->display_query = $this->import_text;
            $this->error = false; // unset error marker, it was used just to skip processing
        } elseif (! empty($id_bookmark) && $_REQUEST['action_bookmark'] == 1) {
            $message = PMA_Message::notice(__('Showing bookmark'));
        } elseif ($this->bookmark_created) {
            $this->special_message = '[br]'  . sprintf(
                    __('Bookmark %s has been created.'),
                    htmlspecialchars($_POST['bkm_label'])
                );
        } elseif ($this->finished && ! $this->error) {
            if ($this->import_type == 'query') {
                $message = PMA_Message::success();
            } else {
                $message = PMA_Message::success(
                    '<em>'
                    . __('Import has been successfully finished, %d queries executed.')
                    . '</em>'
                );
                $message->addParam($this->executed_queries);

                if ($this->import_notice) {
                    $message->addString($this->import_notice);
                }
                if (isset($this->local_import_file)) {
                    $message->addString('(' . htmlspecialchars($this->local_import_file) . ')');
                } else {
                    $message->addString(
                        '(' . htmlspecialchars($_FILES['import_file']['name']) . ')'
                    );
                }
            }
        }

        // Did we hit timeout? Tell it user.
        if ($this->timeout_passed) {
            $importUrl = $err_url .= '&timeout_passed=1&offset=' . urlencode(
                    $GLOBALS['offset']
                );
            if (isset($this->local_import_file)) {
                $importUrl .= '&local_import_file=' . urlencode($this->local_import_file);
            }
            $message = PMA_Message::error(
                __(
                    'Script timeout passed, if you want to finish import,'
                    . ' please %sresubmit the same file%s and import will resume.'
                )
            );
            $message->addParam('<a href="' . $importUrl . '">', false);
            $message->addParam('</a>', false);

            if ($this->offset == 0 || (isset($original_skip) && $original_skip == $this->offset)) {
                $message->addString(
                    __(
                        'However on last run no data has been parsed,'
                        . ' this usually means phpMyAdmin won\'t be able to'
                        . ' finish this import unless you increase php time limits.'
                    )
                );
            }
        }

        // if there is any message, copy it into $_SESSION as well,
        // so we can obtain it by AJAX call
        if (isset($message)) {
            $_SESSION['Import_message']['message'] = $message->getDisplay();
        }
        // Parse and analyze the query, for correct db and table name
        // in case of a query typed in the query window
        // (but if the query is too large, in case of an imported file, the parser
        //  can choke on it so avoid parsing)
        $sqlLength = /*overload*/mb_strlen($this->sql_query);
        if ($sqlLength <= $GLOBALS['cfg']['MaxCharactersInDisplayedSQL']) {
            $sql_query = $this->sql_query;
            $analyzed_sql_results = array();
            include_once 'libraries/parse_analyze.inc.php';
            $this->analyzed_sql_results = $analyzed_sql_results;
        }

        // There was an error?
        if (isset($this->my_die)) {
            foreach ($this->my_die as $key => $die) {
                PMA_Util::mysqlDie(
                    $die['error'], $die['sql'], false, $err_url, $this->error
                );
            }
        }

        if ($this->go_sql) {

            if (! empty($sql_data) && ($sql_data['valid_queries'] > 1)) {
                $_SESSION['is_multi_query'] = true;
                $sql_queries = $sql_data['valid_sql'];
            } else {
                $sql_queries = array($this->sql_query);
            }

            $html_output = '';
            foreach ($sql_queries as $this->sql_query) {
                // parse sql query
                include 'libraries/parse_analyze.inc.php';

                $html_output .= PMA_executeQueryAndGetQueryResponse(
                    $this->analyzed_sql_results, // analyzed_sql_results
                    false, // is_gotofile
                    $this->db, // db
                    $this->table, // table
                    null, // find_real_end
                    $this->sql_query, // sql_query_for_bookmark
                    null, // extra_data
                    null, // message_to_show
                    null, // message
                    null, // sql_data
                    $goto, // goto
                    $this->pmaThemeImage, // pmaThemeImage
                    null, // disp_query
                    null, // disp_message
                    null, // query_type
                    $this->sql_query, // sql_query
                    null, // selectedTables
                    null // complete_query
                );
            }

            $response = $this->response;
            $response->addJSON('ajax_reload', $ajax_reload);
            $response->addHTML($html_output);
            return;

        } else if ($this->result) {
            // Save a Bookmark with more than one queries (if Bookmark label given).
            if (! empty($_POST['bkm_label']) && ! empty($import_text)) {
                $cfgBookmark = PMA_Bookmark_getParams();
                PMA_storeTheQueryAsBookmark(
                    $this->db, $cfgBookmark['user'],
                    $_REQUEST['sql_query'], $_POST['bkm_label'],
                    isset($_POST['bkm_replace']) ? $_POST['bkm_replace'] : null
                );
            }

            $response = $this->response;
            $response->isSuccess(true);
            $response->addJSON('message', PMA_Message::success($this->msg));
            $response->addJSON(
                'sql_query',
                PMA_Util::getMessage($this->msg, $this->sql_query, 'success')
            );
        } else if ($this->result == false) {
            $response = $this->response;
            $response->isSuccess(false);
            $response->addJSON('message', PMA_Message::error($this->msg));
        } else {
            $active_page = $goto;
            include '' . $goto;
        }

        // If there is request for ROLLBACK in the end.
        if (isset($_REQUEST['rollback_query'])) {
            $this->dbi->query('ROLLBACK');
        }
    }

    /**
     * Checks whether timeout is getting close
     *
     * @return boolean true if timeout is close
     * @access public
     */
    function checkTimeout()
    {
        if ($this->maximum_time == 0) {
            return false;
        } elseif ($this->timeout_passed) {
            return true;
            /* 5 in next row might be too much */
        } elseif ((time() - $this->timestamp) > ($this->maximum_time - 5)) {
            $this->timeout_passed = true;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Detects what compression the file uses
     *
     * @param string $filepath filename to check
     *
     * @return string MIME type of compression, none for none
     * @access public
     */
    function detectCompression($filepath)
    {
        $file = @fopen($filepath, 'rb');
        if (!$file) {
            return false;
        }
        return PMA_Util::getCompressionMimeType($file);
    }

    /**
     * Runs query inside import buffer. This is needed to allow displaying
     * of last SELECT, SHOW or HANDLER results and similar nice stuff.
     *
     * @param string $sql query to run
     * @param string $full query to display, this might be commented
     * @param bool $controluser whether to use control user for queries
     * @param array &$sql_data SQL parse data storage
     *
     * @return void
     * @access public
     */
    function importRunQuery($sql = '', $full = '', $controluser = false,
                            &$sql_data = array()
    ) {
        $this->read_multiply = 1;
        if (!isset($this->import_run_buffer)) {
            // Do we have something to push into buffer?
            $this->import_run_buffer = $this->ImportRunQuery_post(
                $this->import_run_buffer, $sql, $full
            );
            return;
        }

        // Should we skip something?
        if ($this->skip_queries > 0) {
            $this->skip_queries--;
            // Do we have something to push into buffer?
            $this->import_run_buffer = $this->ImportRunQuery_post(
                $this->import_run_buffer, $sql, $full
            );
            return;
        }

        if (!empty($this->import_run_buffer['sql'])
            && trim($this->import_run_buffer['sql']) != ''
        ) {

            // USE query changes the database, son need to track
            // while running multiple queries
            $is_use_query
                = (/*overload*/
                mb_stripos($this->import_run_buffer['sql'], "use ") !== false)
                ? true
                : false;

            $this->max_sql_len = max(
                $this->max_sql_len,
                /*overload*/
                mb_strlen($this->import_run_buffer['sql'])
            );
            if (!$this->sql_query_disabled) {
                $this->sql_query .= $this->import_run_buffer['full'];
            }
            $pattern = '@^[[:space:]]*DROP[[:space:]]+(IF EXISTS[[:space:]]+)?'
                . 'DATABASE @i';
            if (!$this->cfg['AllowUserDropDatabase']
                && !$this->is_superuser
                && preg_match($pattern, $this->import_run_buffer['sql'])
            ) {
                $GLOBALS['message'] = PMA_Message::error(
                    __('"DROP DATABASE" statements are disabled.')
                );
                $this->error = true;
            } else {
                $this->executed_queries++;

                $pattern = '/^[\s]*(SELECT|SHOW|HANDLER)/i';
                if ($this->run_query
                    && $GLOBALS['finished']
                    && empty($sql)
                    && !$this->error
                    && ((!empty($this->import_run_buffer['sql'])
                            && preg_match($pattern, $this->import_run_buffer['sql']))
                        || ($this->executed_queries == 1))
                ) {
                    $this->go_sql = true;
                    if (!$this->sql_query_disabled) {
                        $this->complete_query = $this->sql_query;
                        $this->display_query = $this->sql_query;
                    } else {
                        $this->complete_query = '';
                        $this->display_query = '';
                    }
                    $this->sql_query = $this->import_run_buffer['sql'];
                    $sql_data['valid_sql'][] = $this->import_run_buffer['sql'];
                    if (!isset($sql_data['valid_queries'])) {
                        $sql_data['valid_queries'] = 0;
                    }
                    $sql_data['valid_queries']++;

                    // If a 'USE <db>' SQL-clause was found,
                    // set our current $this->db to the new one
                    list($this->db, $this->reload) = $this->lookForUse(
                        $this->import_run_buffer['sql'],
                        $this->db,
                        $this->reload
                    );
                } elseif ($this->run_query) {

                    if ($controluser) {
                        $this->result = PMA_queryAsControlUser(
                            $this->import_run_buffer['sql']
                        );
                    } else {
                        $this->result = $this->dbi->tryQuery($this->import_run_buffer['sql']);
                    }

                    $this->msg = '# ';
                    if ($this->result === false) { // execution failed
                        if (!isset($this->my_die)) {
                            $this->my_die = array();
                        }
                        $this->my_die[] = array(
                            'sql' => $this->import_run_buffer['full'],
                            'error' => $this->dbi->getError()
                        );

                        $this->msg .= __('Error');

                        if (!$this->cfg['IgnoreMultiSubmitErrors']) {
                            $this->error = true;
                            return;
                        }
                    } else {
                        $a_num_rows = (int)@$this->dbi->numRows($this->result);
                        $a_aff_rows = (int)@$this->dbi->affectedRows();
                        if ($a_num_rows > 0) {
                            $this->msg .= __('Rows') . ': ' . $a_num_rows;
                            $this->last_query_with_results = $this->import_run_buffer['sql'];
                        } elseif ($a_aff_rows > 0) {
                            $message = PMA_Message::getMessageForAffectedRows(
                                $a_aff_rows
                            );
                            $this->msg .= $message->getMessage();
                        } else {
                            $this->msg .= __(
                                'MySQL returned an empty result set (i.e. zero '
                                . 'rows).'
                            );
                        }

                        $sql_data = $this->updateSqlData(
                            $sql_data, $a_num_rows, $is_use_query, $this->import_run_buffer
                        );
                    }
                    if (!$this->sql_query_disabled) {
                        $this->sql_query .= $this->msg . "\n";
                    }

                    // If a 'USE <db>' SQL-clause was found and the query
                    // succeeded, set our current $this->db to the new one
                    if ($this->result != false) {
                        list($this->db, $this->reload) = $this->lookForUse(
                            $this->import_run_buffer['sql'],
                            $this->db,
                            $this->reload
                        );
                    }

                    $pattern = '@^[\s]*(DROP|CREATE)[\s]+(IF EXISTS[[:space:]]+)'
                        . '?(TABLE|DATABASE)[[:space:]]+(.+)@im';
                    if ($this->result != false
                        && preg_match($pattern, $this->import_run_buffer['sql'])
                    ) {
                        $this->reload = true;
                    }
                } // end run query
            } // end if not DROP DATABASE
            // end non empty query
        } elseif (!empty($this->import_run_buffer['full'])) {
            if ($this->go_sql) {
                $this->complete_query .= $this->import_run_buffer['full'];
                $this->display_query .= $this->import_run_buffer['full'];
            } else {
                if (!$this->sql_query_disabled) {
                    $this->sql_query .= $this->import_run_buffer['full'];
                }
            }
        }
        // check length of query unless we decided to pass it to sql.php
        // (if $this->run_query is false, we are just displaying so show
        // the complete query in the textarea)
        if (!$this->go_sql && $this->run_query) {
            if (!empty($this->sql_query)) {
                if (/*overload*/
                    mb_strlen($this->sql_query) > 50000
                    || $this->executed_queries > 50
                    || $this->max_sql_len > 1000
                ) {
                    $this->sql_query = '';
                    $this->sql_query_disabled = true;
                }
            }
        }

        // Do we have something to push into buffer?
        $this->import_run_buffer = $this->ImportRunQuery_post($this->import_run_buffer, $sql, $full);

        // In case of ROLLBACK, notify the user.
        if (isset($_REQUEST['rollback_query'])) {
            $this->msg .= __('[ROLLBACK occurred.]');
        }
    }

    /**
     * Update $sql_data
     *
     * @param array $sql_data SQL data
     * @param int $a_num_rows Number of rows
     * @param bool $is_use_query Query is used
     * @param array $import_run_buffer Import buffer
     *
     * @return array
     */
    function updateSqlData($sql_data, $a_num_rows, $is_use_query, $import_run_buffer)
    {
        if (($a_num_rows > 0) || $is_use_query) {
            $sql_data['valid_sql'][] = $import_run_buffer['sql'];
            if (!isset($sql_data['valid_queries'])) {
                $sql_data['valid_queries'] = 0;
            }
            $sql_data['valid_queries']++;
        }
        return $sql_data;
    }

    /**
     * Return import run buffer
     *
     * @param array $import_run_buffer Buffer of queries for import
     * @param string $sql SQL query
     * @param string $full Query to display
     *
     * @return array Buffer of queries for import
     */
    function ImportRunQuery_post($import_run_buffer, $sql, $full)
    {
        if (!empty($sql) || !empty($full)) {
            $import_run_buffer = array('sql' => $sql, 'full' => $full);
            return $import_run_buffer;
        } else {
            unset($GLOBALS['import_run_buffer']);
            return $import_run_buffer;
        }
    }

    /**
     * Looks for the presence of USE to possibly change current db
     *
     * @param string $buffer buffer to examine
     * @param string $db current db
     * @param bool $reload reload
     *
     * @return array (current or new db, whether to reload)
     * @access public
     */
    function lookForUse($buffer, $db, $reload)
    {
        if (preg_match('@^[\s]*USE[[:space:]]+([\S]+)@i', $buffer, $match)) {
            $db = trim($match[1]);
            $db = trim($db, ';'); // for example, USE abc;

            // $db must not contain the escape characters generated by backquote()
            // ( used in $this->buildSQL() as: backquote($db_name), and then called
            // in $this->importRunQuery() which in turn calls $this->lookForUse() )
            $db = PMA_Util::unQuote($db);

            $reload = true;
        }
        return (array($db, $reload));
    }


    /**
     * Returns next part of imported file/buffer
     *
     * @param int $size size of buffer to read
     *                  (this is maximal size function will return)
     *
     * @return string part of file/buffer
     * @access public
     */
    function importGetNextChunk($size = 32768)
    {
        // Add some progression while reading large amount of data
        if ($this->read_multiply <= 8) {
            $size *= $this->read_multiply;
        } else {
            $size *= 8;
        }
        $this->read_multiply++;

        // We can not read too much
        if ($size > $GLOBALS['read_limit']) {
            $size = $GLOBALS['read_limit'];
        }

        if ($this->checkTimeout()) {
            return false;
        }
        if ($GLOBALS['finished']) {
            return true;
        }

        if ($GLOBALS['import_file'] == 'none') {
            // Well this is not yet supported and tested,
            // but should return content of textarea
            if (/*overload*/
                mb_strlen($GLOBALS['import_text']) < $size
            ) {
                $GLOBALS['finished'] = true;
                return $GLOBALS['import_text'];
            } else {
                $r = /*overload*/
                    mb_substr($GLOBALS['import_text'], 0, $size);
                $GLOBALS['offset'] += $size;
                $GLOBALS['import_text'] = /*overload*/
                    mb_substr($GLOBALS['import_text'], $size);
                return $r;
            }
        }

        $this->result = '';

        switch ($this->compression) {
            case 'application/bzip2':
                $this->result = bzread($this->import_handle, $size);
                $GLOBALS['finished'] = feof($this->import_handle);
                break;
            case 'application/gzip':
                $this->result = gzread($this->import_handle, $size);
                $GLOBALS['finished'] = feof($this->import_handle);
                break;
            case 'application/zip':
                $this->result = /*overload*/
                    mb_substr($GLOBALS['import_text'], 0, $size);
                $GLOBALS['import_text'] = /*overload*/
                    mb_substr(
                        $GLOBALS['import_text'],
                        $size
                    );
                $GLOBALS['finished'] = empty($GLOBALS['import_text']);
                break;
            case 'none':
                $this->result = fread($this->import_handle, $size);
                $GLOBALS['finished'] = feof($this->import_handle);
                break;
        }
        $GLOBALS['offset'] += $size;

        if ($this->charset_conversion) {
            return PMA_convertString($this->charset_of_file, 'utf-8', $this->result);
        }

        /**
         * Skip possible byte order marks (I do not think we need more
         * charsets, but feel free to add more, you can use wikipedia for
         * reference: <http://en.wikipedia.org/wiki/Byte_Order_Mark>)
         *
         * @todo BOM could be used for charset autodetection
         */
        if ($GLOBALS['offset'] == $size) {
            // UTF-8
            if (strncmp($this->result, "\xEF\xBB\xBF", 3) == 0) {
                $this->result = /*overload*/
                    mb_substr($this->result, 3);
                // UTF-16 BE, LE
            } elseif (strncmp($this->result, "\xFE\xFF", 2) == 0
                || strncmp($this->result, "\xFF\xFE", 2) == 0
            ) {
                $this->result = /*overload*/
                    mb_substr($this->result, 2);
            }
        }
        return $this->result;
    }

    /**
     * Returns the "Excel" column name (i.e. 1 = "A", 26 = "Z", 27 = "AA", etc.)
     *
     * This functions uses recursion to build the Excel column name.
     *
     * The column number (1-26) is converted to the responding
     * ASCII character (A-Z) and returned.
     *
     * If the column number is bigger than 26 (= num of letters in alphabet),
     * an extra character needs to be added. To find this extra character,
     * the number is divided by 26 and this value is passed to another instance
     * of the same function (hence recursion). In that new instance the number is
     * evaluated again, and if it is still bigger than 26, it is divided again
     * and passed to another instance of the same function. This continues until
     * the number is smaller than 26. Then the last called function returns
     * the corresponding ASCII character to the function that called it.
     * Each time a called function ends an extra character is added to the column name.
     * When the first function is reached, the last character is added and the complete
     * column name is returned.
     *
     * @param int $num the column number
     *
     * @return string The column's "Excel" name
     * @access  public
     */
    function getColumnAlphaName($num)
    {
        $A = 65; // ASCII value for capital "A"
        $col_name = "";

        if ($num > 26) {
            $div = (int)($num / 26);
            $remain = (int)($num % 26);

            // subtract 1 of divided value in case the modulus is 0,
            // this is necessary because A-Z has no 'zero'
            if ($remain == 0) {
                $div--;
            }

            // recursive function call
            $col_name = $this->getColumnAlphaName($div);
            // use modulus as new column number
            $num = $remain;
        }

        if ($num == 0) {
            // use 'Z' if column number is 0,
            // this is necessary because A-Z has no 'zero'
            $col_name .= /*overload*/
                mb_chr(($A + 26) - 1);
        } else {
            // convert column number to ASCII character
            $col_name .= /*overload*/
                mb_chr(($A + $num) - 1);
        }

        return $col_name;
    }

    /**
     * Returns the column number based on the Excel name.
     * So "A" = 1, "Z" = 26, "AA" = 27, etc.
     *
     * Basically this is a base26 (A-Z) to base10 (0-9) conversion.
     * It iterates through all characters in the column name and
     * calculates the corresponding value, based on character value
     * (A = 1, ..., Z = 26) and position in the string.
     *
     * @param string $name column name(i.e. "A", or "BC", etc.)
     *
     * @return int The column number
     * @access  public
     */
    function getColumnNumberFromName($name)
    {
        if (empty($name)) {
            return 0;
        }

        $name = /*overload*/
            mb_strtoupper($name);
        $num_chars = /*overload*/
            mb_strlen($name);
        $column_number = 0;
        for ($i = 0; $i < $num_chars; ++$i) {
            // read string from back to front
            $char_pos = ($num_chars - 1) - $i;

            // convert capital character to ASCII value
            // and subtract 64 to get corresponding decimal value
            // ASCII value of "A" is 65, "B" is 66, etc.
            // Decimal equivalent of "A" is 1, "B" is 2, etc.
            $number = (int)(/*overload*/
                mb_ord($name[$char_pos]) - 64);

            // base26 to base10 conversion : multiply each number
            // with corresponding value of the position, in this case
            // $i=0 : 1; $i=1 : 26; $i=2 : 676; ...
            $column_number += $number * PMA_Util::pow(26, $i);
        }
        return $column_number;
    }

    /**
     * Obtains the precision (total # of digits) from a size of type decimal
     *
     * @param string $last_cumulative_size Size of type decimal
     *
     * @return int Precision of the given decimal size notation
     * @access  public
     */
    function getDecimalPrecision($last_cumulative_size)
    {
        return (int)substr(
            $last_cumulative_size,
            0,
            strpos($last_cumulative_size, ",")
        );
    }

    /**
     * Obtains the scale (# of digits to the right of the decimal point)
     * from a size of type decimal
     *
     * @param string $last_cumulative_size Size of type decimal
     *
     * @return int Scale of the given decimal size notation
     * @access  public
     */
    function getDecimalScale($last_cumulative_size)
    {
        return (int)substr(
            $last_cumulative_size,
            (strpos($last_cumulative_size, ",") + 1),
            (strlen($last_cumulative_size) - strpos($last_cumulative_size, ","))
        );
    }

    /**
     * Obtains the decimal size of a given cell
     *
     * @param string $cell cell content
     *
     * @return array Contains the precision, scale, and full size
     *                representation of the given decimal cell
     * @access  public
     */
    function getDecimalSize($cell)
    {
        $curr_size = /*overload*/
            mb_strlen((string)$cell);
        $decPos = /*overload*/
            mb_strpos($cell, ".");
        $decPrecision = ($curr_size - 1) - $decPos;

        $m = $curr_size - 1;
        $d = $decPrecision;

        return array($m, $d, ($m . "," . $d));
    }

    /**
     * Obtains the size of the given cell
     *
     * @param string $last_cumulative_size Last cumulative column size
     * @param int $last_cumulative_type Last cumulative column type
     *                                     (ImportController::NONE or ImportController::VARCHAR or ImportController::DECIMAL or ImportController::INT or ImportController::BIGINT)
     * @param int $curr_type Type of the current cell
     *                                     (ImportController::NONE or ImportController::VARCHAR or ImportController::DECIMAL or ImportController::INT or ImportController::BIGINT)
     * @param string $cell The current cell
     *
     * @return string  Size of the given cell in the type-appropriate format
     * @access  public
     *
     * @todo    Handle the error cases more elegantly
     */
    function detectSize($last_cumulative_size, $last_cumulative_type,
                        $curr_type, $cell
    )
    {
        $curr_size = /*overload*/
            mb_strlen((string)$cell);

        /**
         * If the cell is NULL, don't treat it as a varchar
         */
        if (!strcmp('NULL', $cell)) {
            return $last_cumulative_size;
        } elseif ($curr_type == ImportController::VARCHAR) {
            /**
             * What to do if the current cell is of type ImportController::VARCHAR
             */
            /**
             * The last cumulative type was ImportController::VARCHAR
             */
            if ($last_cumulative_type == ImportController::VARCHAR) {
                if ($curr_size >= $last_cumulative_size) {
                    return $curr_size;
                } else {
                    return $last_cumulative_size;
                }
            } elseif ($last_cumulative_type == ImportController::DECIMAL) {
                /**
                 * The last cumulative type was ImportController::DECIMAL
                 */
                $oldM = $this->getDecimalPrecision($last_cumulative_size);

                if ($curr_size >= $oldM) {
                    return $curr_size;
                } else {
                    return $oldM;
                }
            } elseif ($last_cumulative_type == ImportController::BIGINT || $last_cumulative_type == ImportController::INT) {
                /**
                 * The last cumulative type was ImportController::BIGINT or ImportController::INT
                 */
                if ($curr_size >= $last_cumulative_size) {
                    return $curr_size;
                } else {
                    return $last_cumulative_size;
                }
            } elseif (!isset($last_cumulative_type) || $last_cumulative_type == ImportController::NONE) {
                /**
                 * This is the first row to be analyzed
                 */
                return $curr_size;
            } else {
                /**
                 * An error has DEFINITELY occurred
                 */
                /**
                 * TODO: Handle this MUCH more elegantly
                 */

                return -1;
            }
        } elseif ($curr_type == ImportController::DECIMAL) {
            /**
             * What to do if the current cell is of type ImportController::DECIMAL
             */
            /**
             * The last cumulative type was ImportController::VARCHAR
             */
            if ($last_cumulative_type == ImportController::VARCHAR) {
                /* Convert $last_cumulative_size from varchar to decimal format */
                $size = $this->getDecimalSize($cell);

                if ($size[ImportController::M] >= $last_cumulative_size) {
                    return $size[ImportController::M];
                } else {
                    return $last_cumulative_size;
                }
            } elseif ($last_cumulative_type == ImportController::DECIMAL) {
                /**
                 * The last cumulative type was ImportController::DECIMAL
                 */
                $size = $this->getDecimalSize($cell);

                $oldM = $this->getDecimalPrecision($last_cumulative_size);
                $oldD = $this->getDecimalScale($last_cumulative_size);

                /* New val if ImportController::M or ImportController::D is greater than current largest */
                if ($size[ImportController::M] > $oldM || $size[ImportController::D] > $oldD) {
                    /* Take the largest of both types */
                    return (string)((($size[ImportController::M] > $oldM) ? $size[ImportController::M] : $oldM)
                        . "," . (($size[ImportController::D] > $oldD) ? $size[ImportController::D] : $oldD));
                } else {
                    return $last_cumulative_size;
                }
            } elseif ($last_cumulative_type == ImportController::BIGINT || $last_cumulative_type == ImportController::INT) {
                /**
                 * The last cumulative type was ImportController::BIGINT or ImportController::INT
                 */
                /* Convert $last_cumulative_size from int to decimal format */
                $size = $this->getDecimalSize($cell);

                if ($size[ImportController::M] >= $last_cumulative_size) {
                    return $size[ImportController::FULL];
                } else {
                    return ($last_cumulative_size . "," . $size[ImportController::D]);
                }
            } elseif (!isset($last_cumulative_type) || $last_cumulative_type == ImportController::NONE) {
                /**
                 * This is the first row to be analyzed
                 */
                /* First row of the column */
                $size = $this->getDecimalSize($cell);

                return $size[ImportController::FULL];
            } else {
                /**
                 * An error has DEFINITELY occurred
                 */
                /**
                 * TODO: Handle this MUCH more elegantly
                 */

                return -1;
            }
        } elseif ($curr_type == ImportController::BIGINT || $curr_type == ImportController::INT) {
            /**
             * What to do if the current cell is of type ImportController::BIGINT or ImportController::INT
             */
            /**
             * The last cumulative type was ImportController::VARCHAR
             */
            if ($last_cumulative_type == ImportController::VARCHAR) {
                if ($curr_size >= $last_cumulative_size) {
                    return $curr_size;
                } else {
                    return $last_cumulative_size;
                }
            } elseif ($last_cumulative_type == ImportController::DECIMAL) {
                /**
                 * The last cumulative type was ImportController::DECIMAL
                 */
                $oldM = $this->getDecimalPrecision($last_cumulative_size);
                $oldD = $this->getDecimalScale($last_cumulative_size);
                $oldInt = $oldM - $oldD;
                $newInt = /*overload*/
                    mb_strlen((string)$cell);

                /* See which has the larger integer length */
                if ($oldInt >= $newInt) {
                    /* Use old decimal size */
                    return $last_cumulative_size;
                } else {
                    /* Use $newInt + $oldD as new ImportController::M */
                    return (($newInt + $oldD) . "," . $oldD);
                }
            } elseif ($last_cumulative_type == ImportController::BIGINT || $last_cumulative_type == ImportController::INT) {
                /**
                 * The last cumulative type was ImportController::BIGINT or ImportController::INT
                 */
                if ($curr_size >= $last_cumulative_size) {
                    return $curr_size;
                } else {
                    return $last_cumulative_size;
                }
            } elseif (!isset($last_cumulative_type) || $last_cumulative_type == ImportController::NONE) {
                /**
                 * This is the first row to be analyzed
                 */
                return $curr_size;
            } else {
                /**
                 * An error has DEFINITELY occurred
                 */
                /**
                 * TODO: Handle this MUCH more elegantly
                 */

                return -1;
            }
        } else {
            /**
             * An error has DEFINITELY occurred
             */
            /**
             * TODO: Handle this MUCH more elegantly
             */

            return -1;
        }
    }

    /**
     * Determines what MySQL type a cell is
     *
     * @param int $last_cumulative_type Last cumulative column type
     *                                     (ImportController::VARCHAR or ImportController::INT or ImportController::BIGINT or ImportController::DECIMAL or ImportController::NONE)
     * @param string $cell String representation of the cell for which
     *                                     a best-fit type is to be determined
     *
     * @return int  The MySQL type representation
     *               (ImportController::VARCHAR or ImportController::INT or ImportController::BIGINT or ImportController::DECIMAL or ImportController::NONE)
     * @access  public
     */
    function detectType($last_cumulative_type, $cell)
    {
        /**
         * If numeric, determine if decimal, int or bigint
         * Else, we call it varchar for simplicity
         */

        if (!strcmp('NULL', $cell)) {
            if ($last_cumulative_type === null || $last_cumulative_type == ImportController::NONE) {
                return ImportController::NONE;
            }

            return $last_cumulative_type;
        }

        if (!is_numeric($cell)) {
            return ImportController::VARCHAR;
        }

        if ($cell == (string)(float)$cell
            && /*overload*/
            mb_strpos($cell, ".") !== false
            && /*overload*/
            mb_substr_count($cell, ".") == 1
        ) {
            return ImportController::DECIMAL;
        }

        if (abs($cell) > 2147483647) {
            return ImportController::BIGINT;
        }

        return ImportController::INT;
    }

    /**
     * Determines if the column types are int, decimal, or string
     *
     * @param array &$table array(string $table_name, array $col_names, array $rows)
     *
     * @return array    array(array $types, array $sizes)
     * @access  public
     *
     * @link http://wiki.phpmyadmin.net/pma/Import
     *
     * @todo    Handle the error case more elegantly
     */
    function analyzeTable(&$table)
    {
        /* Get number of rows in table */
        $numRows = count($table[ImportController::ROWS]);
        /* Get number of columns */
        $numCols = count($table[ImportController::COL_NAMES]);
        /* Current type for each column */
        $types = array();
        $sizes = array();

        /* Initialize $sizes to all 0's */
        for ($i = 0; $i < $numCols; ++$i) {
            $sizes[$i] = 0;
        }

        /* Initialize $types to ImportController::NONE */
        for ($i = 0; $i < $numCols; ++$i) {
            $types[$i] = ImportController::NONE;
        }

        /* If the passed array is not of the correct form, do not process it */
        if (!is_array($table)
            || is_array($table[ImportController::TBL_NAME])
            || !is_array($table[ImportController::COL_NAMES])
            || !is_array($table[ImportController::ROWS])
        ) {
            /**
             * TODO: Handle this better
             */

            return false;
        }

        /* Analyze each column */
        for ($i = 0; $i < $numCols; ++$i) {
            /* Analyze the column in each row */
            for ($j = 0; $j < $numRows; ++$j) {
                /* Determine type of the current cell */
                $curr_type = $this->detectType($types[$i], $table[ImportController::ROWS][$j][$i]);
                /* Determine size of the current cell */
                $sizes[$i] = $this->detectSize(
                    $sizes[$i],
                    $types[$i],
                    $curr_type,
                    $table[ImportController::ROWS][$j][$i]
                );

                /**
                 * If a type for this column has already been declared,
                 * only alter it if it was a number and a varchar was found
                 */
                if ($curr_type != ImportController::NONE) {
                    if ($curr_type == ImportController::VARCHAR) {
                        $types[$i] = ImportController::VARCHAR;
                    } else if ($curr_type == ImportController::DECIMAL) {
                        if ($types[$i] != ImportController::VARCHAR) {
                            $types[$i] = ImportController::DECIMAL;
                        }
                    } else if ($curr_type == ImportController::BIGINT) {
                        if ($types[$i] != ImportController::VARCHAR && $types[$i] != ImportController::DECIMAL) {
                            $types[$i] = ImportController::BIGINT;
                        }
                    } else if ($curr_type == ImportController::INT) {
                        if ($types[$i] != ImportController::VARCHAR
                            && $types[$i] != ImportController::DECIMAL
                            && $types[$i] != ImportController::BIGINT
                        ) {
                            $types[$i] = ImportController::INT;
                        }
                    }
                }
            }
        }

        /* Check to ensure that all types are valid */
        $len = count($types);
        for ($n = 0; $n < $len; ++$n) {
            if (!strcmp(ImportController::NONE, $types[$n])) {
                $types[$n] = ImportController::VARCHAR;
                $sizes[$n] = '10';
            }
        }

        return array($types, $sizes);
    }

    /**
     * Builds and executes SQL statements to create the database and tables
     * as necessary, as well as insert all the data.
     *
     * @param string $db_name Name of the database
     * @param array &$tables Array of tables for the specified database
     * @param array &$analyses Analyses of the tables
     * @param array &$additional_sql Additional SQL statements to be executed
     * @param array $options Associative array of options
     *
     * @return void
     * @access  public
     *
     * @link http://wiki.phpmyadmin.net/pma/Import
     */
    function buildSQL($db_name, &$tables, &$analyses = null,
                      &$additional_sql = null, $options = null
    )
    {
        /* Take care of the options */
        if (isset($options['db_collation']) && !is_null($options['db_collation'])) {
            $collation = $options['db_collation'];
        } else {
            $collation = "utf8_general_ci";
        }

        if (isset($options['db_charset']) && !is_null($options['db_charset'])) {
            $charset = $options['db_charset'];
        } else {
            $charset = "utf8";
        }

        if (isset($options['create_db'])) {
            $create_db = $options['create_db'];
        } else {
            $create_db = true;
        }

        /* Create SQL code to handle the database */
        $sql = array();

        if ($create_db) {
            if (PMA_DRIZZLE) {
                $sql[] = "CREATE DATABASE IF NOT EXISTS " . PMA_Util::backquote($db_name)
                    . " COLLATE " . $collation . ";";
            } else {
                $sql[] = "CREATE DATABASE IF NOT EXISTS " . PMA_Util::backquote($db_name)
                    . " DEFAULT CHARACTER SET " . $charset . " COLLATE " . $collation . ";";
            }
        }

        /**
         * The calling plug-in should include this statement,
         * if necessary, in the $additional_sql parameter
         *
         * $sql[] = "USE " . backquote($db_name);
         */

        /* Execute the SQL statements create above */
        $sql_len = count($sql);
        for ($i = 0; $i < $sql_len; ++$i) {
            $this->importRunQuery($sql[$i], $sql[$i]);
        }

        /* No longer needed */
        unset($sql);

        /* Run the $additional_sql statements supplied by the caller plug-in */
        if ($additional_sql != null) {
            /* Clean the SQL first */
            $additional_sql_len = count($additional_sql);

            /**
             * Only match tables for now, because CREATE IF NOT EXISTS
             * syntax is lacking or nonexisting for views, triggers,
             * functions, and procedures.
             *
             * See: http://bugs.mysql.com/bug.php?id=15287
             *
             * To the best of my knowledge this is still an issue.
             *
             * $pattern = 'CREATE (TABLE|VIEW|TRIGGER|FUNCTION|PROCEDURE)';
             */
            $pattern = '/CREATE [^`]*(TABLE)/';
            $replacement = 'CREATE \\1 IF NOT EXISTS';

            /* Change CREATE statements to CREATE IF NOT EXISTS to support
             * inserting into existing structures
             */
            for ($i = 0; $i < $additional_sql_len; ++$i) {
                $additional_sql[$i] = preg_replace(
                    $pattern,
                    $replacement,
                    $additional_sql[$i]
                );
                /* Execute the resulting statements */
                $this->importRunQuery($additional_sql[$i], $additional_sql[$i]);
            }
        }

        if ($analyses != null) {
            $type_array = array(
                ImportController::NONE => "NULL",
                ImportController::VARCHAR => "varchar",
                ImportController::INT => "int",
                ImportController::DECIMAL => "decimal",
                ImportController::BIGINT => "bigint",
                ImportController::GEOMETRY => 'geometry'
            );

            /* TODO: Do more checking here to make sure they really are matched */
            if (count($tables) != count($analyses)) {
                exit();
            }

            /* Create SQL code to create the tables */
            $num_tables = count($tables);
            for ($i = 0; $i < $num_tables; ++$i) {
                $num_cols = count($tables[$i][ImportController::COL_NAMES]);
                $tempSQLStr = "CREATE TABLE IF NOT EXISTS "
                    . PMA_Util::backquote($db_name)
                    . '.' . PMA_Util::backquote($tables[$i][ImportController::TBL_NAME]) . " (";
                for ($j = 0; $j < $num_cols; ++$j) {
                    $size = $analyses[$i][ImportController::SIZES][$j];
                    if ((int)$size == 0) {
                        $size = 10;
                    }

                    $tempSQLStr .= PMA_Util::backquote($tables[$i][ImportController::COL_NAMES][$j]) . " "
                        . $type_array[$analyses[$i][ImportController::TYPES][$j]];
                    if ($analyses[$i][ImportController::TYPES][$j] != ImportController::GEOMETRY) {
                        $tempSQLStr .= "(" . $size . ")";
                    }

                    if ($j != (count($tables[$i][ImportController::COL_NAMES]) - 1)) {
                        $tempSQLStr .= ", ";
                    }
                }
                $tempSQLStr .= ")"
                    . (PMA_DRIZZLE ? "" : " DEFAULT CHARACTER SET " . $charset)
                    . " COLLATE " . $collation . ";";

                /**
                 * Each SQL statement is executed immediately
                 * after it is formed so that we don't have
                 * to store them in a (possibly large) buffer
                 */
                $this->importRunQuery($tempSQLStr, $tempSQLStr);
            }
        }

        /**
         * Create the SQL statements to insert all the data
         *
         * Only one insert query is formed for each table
         */
        $tempSQLStr = "";
        $col_count = 0;
        $num_tables = count($tables);
        for ($i = 0; $i < $num_tables; ++$i) {
            $num_cols = count($tables[$i][ImportController::COL_NAMES]);
            $num_rows = count($tables[$i][ImportController::ROWS]);

            $tempSQLStr = "INSERT INTO " . PMA_Util::backquote($db_name) . '.'
                . PMA_Util::backquote($tables[$i][ImportController::TBL_NAME]) . " (";

            for ($m = 0; $m < $num_cols; ++$m) {
                $tempSQLStr .= PMA_Util::backquote($tables[$i][ImportController::COL_NAMES][$m]);

                if ($m != ($num_cols - 1)) {
                    $tempSQLStr .= ", ";
                }
            }

            $tempSQLStr .= ") VALUES ";

            for ($j = 0; $j < $num_rows; ++$j) {
                $tempSQLStr .= "(";

                for ($k = 0; $k < $num_cols; ++$k) {
                    // If fully formatted SQL, no need to enclose
                    // with apostrophes, add slashes etc.
                    if ($analyses != null
                        && isset($analyses[$i][ImportController::FORMATTEDSQL][$col_count])
                        && $analyses[$i][ImportController::FORMATTEDSQL][$col_count] == true
                    ) {
                        $tempSQLStr .= (string)$tables[$i][ImportController::ROWS][$j][$k];
                    } else {
                        if ($analyses != null) {
                            $is_varchar = ($analyses[$i][ImportController::TYPES][$col_count] === ImportController::VARCHAR);
                        } else {
                            $is_varchar = !is_numeric($tables[$i][ImportController::ROWS][$j][$k]);
                        }

                        /* Don't put quotes around NULL fields */
                        if (!strcmp($tables[$i][ImportController::ROWS][$j][$k], 'NULL')) {
                            $is_varchar = false;
                        }

                        $tempSQLStr .= (($is_varchar) ? "'" : "");
                        $tempSQLStr .= PMA_Util::sqlAddSlashes(
                            (string)$tables[$i][ImportController::ROWS][$j][$k]
                        );
                        $tempSQLStr .= (($is_varchar) ? "'" : "");
                    }

                    if ($k != ($num_cols - 1)) {
                        $tempSQLStr .= ", ";
                    }

                    if ($col_count == ($num_cols - 1)) {
                        $col_count = 0;
                    } else {
                        $col_count++;
                    }

                    /* Delete the cell after we are done with it */
                    unset($tables[$i][ImportController::ROWS][$j][$k]);
                }

                $tempSQLStr .= ")";

                if ($j != ($num_rows - 1)) {
                    $tempSQLStr .= ",\n ";
                }

                $col_count = 0;
                /* Delete the row after we are done with it */
                unset($tables[$i][ImportController::ROWS][$j]);
            }

            $tempSQLStr .= ";";

            /**
             * Each SQL statement is executed immediately
             * after it is formed so that we don't have
             * to store them in a (possibly large) buffer
             */
            $this->importRunQuery($tempSQLStr, $tempSQLStr);
        }

        /* No longer needed */
        unset($tempSQLStr);

        /**
         * A work in progress
         */

        /* Add the viewable structures from $additional_sql
         * to $tables so they are also displayed
         */
        $view_pattern = '@VIEW `[^`]+`\.`([^`]+)@';
        $table_pattern = '@CREATE TABLE IF NOT EXISTS `([^`]+)`@';
        /* Check a third pattern to make sure its not a "USE `db_name`;" statement */

        $regs = array();

        $inTables = false;

        $additional_sql_len = count($additional_sql);
        for ($i = 0; $i < $additional_sql_len; ++$i) {
            preg_match($view_pattern, $additional_sql[$i], $regs);

            if (count($regs) == 0) {
                preg_match($table_pattern, $additional_sql[$i], $regs);
            }

            if (count($regs)) {
                for ($n = 0; $n < $num_tables; ++$n) {
                    if (!strcmp($regs[1], $tables[$n][ImportController::TBL_NAME])) {
                        $inTables = true;
                        break;
                    }
                }

                if (!$inTables) {
                    $tables[] = array(ImportController::TBL_NAME => $regs[1]);
                }
            }

            /* Reset the array */
            $regs = array();
            $inTables = false;
        }

        $params = array('db' => (string)$db_name);
        $db_url = 'db_structure.php' . PMA_URL_getCommon($params);
        $db_ops_url = 'db_operations.php' . PMA_URL_getCommon($params);

        $message = '<br /><br />';
        $message .= '<strong>' . __('The following structures have either been created or altered. Here you can:') . '</strong><br />';
        $message .= '<ul><li>' . __("View a structure's contents by clicking on its name.") . '</li>';
        $message .= '<li>' . __('Change any of its settings by clicking the corresponding "Options" link.') . '</li>';
        $message .= '<li>' . __('Edit structure by following the "Structure" link.') . '</li>';
        $message .= sprintf(
            '<br /><li><a href="%s" title="%s">%s</a> (<a href="%s" title="%s">%s</a>)</li>',
            $db_url,
            sprintf(
                __('Go to database: %s'),
                htmlspecialchars(PMA_Util::backquote($db_name))
            ),
            htmlspecialchars($db_name),
            $db_ops_url,
            sprintf(
                __('Edit settings for %s'),
                htmlspecialchars(PMA_Util::backquote($db_name))
            ),
            __('Options')
        );

        $message .= '<ul>';

        unset($params);

        $num_tables = count($tables);
        for ($i = 0; $i < $num_tables; ++$i) {
            $params = array(
                'db' => (string)$db_name,
                'table' => (string)$tables[$i][ImportController::TBL_NAME]
            );
            $tbl_url = 'sql.php' . PMA_URL_getCommon($params);
            $tbl_struct_url = 'tbl_structure.php' . PMA_URL_getCommon($params);
            $tbl_ops_url = 'tbl_operations.php' . PMA_URL_getCommon($params);

            unset($params);

            $_table = $this->dbi->getTable($db_name, $tables[$i][ImportController::TBL_NAME]);
            if (!$_table->isView()) {
                $message .= sprintf(
                    '<li><a href="%s" title="%s">%s</a> (<a href="%s" title="%s">%s</a>) (<a href="%s" title="%s">%s</a>)</li>',
                    $tbl_url,
                    sprintf(
                        __('Go to table: %s'),
                        htmlspecialchars(
                            PMA_Util::backquote($tables[$i][ImportController::TBL_NAME])
                        )
                    ),
                    htmlspecialchars($tables[$i][ImportController::TBL_NAME]),
                    $tbl_struct_url,
                    sprintf(
                        __('Structure of %s'),
                        htmlspecialchars(
                            PMA_Util::backquote($tables[$i][ImportController::TBL_NAME])
                        )
                    ),
                    __('Structure'),
                    $tbl_ops_url,
                    sprintf(
                        __('Edit settings for %s'),
                        htmlspecialchars(
                            PMA_Util::backquote($tables[$i][ImportController::TBL_NAME])
                        )
                    ),
                    __('Options')
                );
            } else {
                $message .= sprintf(
                    '<li><a href="%s" title="%s">%s</a></li>',
                    $tbl_url,
                    sprintf(
                        __('Go to view: %s'),
                        htmlspecialchars(
                            PMA_Util::backquote($tables[$i][ImportController::TBL_NAME])
                        )
                    ),
                    htmlspecialchars($tables[$i][ImportController::TBL_NAME])
                );
            }
        }

        $message .= '</ul></ul>';

        global $import_notice;
        $import_notice = $message;

        unset($tables);
    }


    /**
     * Stops the import on (mostly upload/file related) error
     *
     * @param PMA_Message $error_message The error message
     *
     * @return void
     * @access  public
     *
     */
    function stopImport(PMA_Message $error_message)
    {
        // Close open handles
        if ($this->import_handle !== false && $this->import_handle !== null) {
            fclose($this->import_handle);
        }

        // Delete temporary file
        if ($this->file_to_unlink != '') {
            unlink($this->file_to_unlink);
        }
        $this->msg = $error_message->getDisplay();
        $_SESSION['Import_message']['message'] = $this->msg;

        $response = $this->response;
        $response->isSuccess(false);
        $response->addJSON('message', PMA_Message::error($this->msg));

        return;
    }

    /**
     * Handles request for Simulation of UPDATE/DELETE queries.
     *
     * @return void
     */
    function handleSimulateDMLRequest()
    {
        $response = $this->response;
        $this->error = false;
        $error_msg = __('Only single-table UPDATE and DELETE queries can be simulated.');
        $sql_delimiter = $_REQUEST['sql_delimiter'];
        $sql_data = array();
        $queries = explode($sql_delimiter, $GLOBALS['sql_query']);
        foreach ($queries as $this->sql_query) {
            if (empty($this->sql_query)) {
                continue;
            }

            // Parsing the query.
            $parser = new SqlParser\Parser($this->sql_query);

            if (empty($parser->statements[0])) {
                continue;
            }

            $statement = $parser->statements[0];

            $analyzed_sql_results = array(
                'query' => $this->sql_query,
                'parser' => $parser,
                'statement' => $statement,
            );

            if ((!(($statement instanceof SqlParser\Statements\UpdateStatement)
                    || ($statement instanceof SqlParser\Statements\DeleteStatement)))
                || (!empty($statement->join))
            ) {
                $this->error = $error_msg;
                break;
            }

            $tables = SqlParser\Utils\Query::getTables($statement);
            if (count($tables) > 1) {
                $this->error = $error_msg;
                break;
            }

            // Get the matched rows for the query.
            $this->result = $this->getMatchedRows($analyzed_sql_results);
            if (!$this->error = $this->dbi->getError()) {
                $sql_data[] = $this->result;
            } else {
                break;
            }
        }

        if ($this->error) {
            $message = PMA_Message::rawError($this->error);
            $response->addJSON('message', $message);
            $response->addJSON('sql_data', false);
        } else {
            $response->addJSON('sql_data', $sql_data);
        }
    }

    /**
     * Find the matching rows for UPDATE/DELETE query.
     *
     * @param array $analyzed_sql_results Analyzed SQL results from parser.
     *
     * @return mixed
     */
    function getMatchedRows($analyzed_sql_results = array())
    {
        $statement = $analyzed_sql_results['statement'];

        $matched_row_query = '';
        if ($statement instanceof SqlParser\Statements\DeleteStatement) {
            $matched_row_query = $this->getSimulatedDeleteQuery($analyzed_sql_results);
        } elseif ($statement instanceof SqlParser\Statements\UpdateStatement) {
            $matched_row_query = $this->getSimulatedUpdateQuery($analyzed_sql_results);
        }

        // Execute the query and get the number of matched rows.
        $matched_rows = $this->executeMatchedRowQuery($matched_row_query);

        // URL to matched rows.
        $_url_params = array(
            'db' => $GLOBALS['db'],
            'sql_query' => $matched_row_query
        );
        $matched_rows_url = 'sql.php' . PMA_URL_getCommon($_url_params);

        return array(
            'sql_query' => PMA_Util::formatSql($analyzed_sql_results['query']),
            'matched_rows' => $matched_rows,
            'matched_rows_url' => $matched_rows_url
        );
    }

    /**
     * Transforms a UPDATE query into SELECT statement.
     *
     * @param array $analyzed_sql_results Analyzed SQL results from parser.
     *
     * @return string SQL query
     */
    function getSimulatedUpdateQuery($analyzed_sql_results)
    {
        $table_references = SqlParser\Utils\Query::getTables(
            $analyzed_sql_results['statement']
        );

        $where = SqlParser\Utils\Query::getClause(
            $analyzed_sql_results['statement'],
            $analyzed_sql_results['parser']->list,
            'WHERE'
        );

        if (empty($where)) {
            $where = '1';
        }

        $columns = array();
        $diff = array();
        foreach ($analyzed_sql_results['statement']->set as $set) {
            $columns[] = $set->column;
            $diff[] = $set->column . ' <> ' . $set->value;
        }
        if (!empty($diff)) {
            $where .= ' AND (' . implode(' OR ', $diff) . ')';
        }

        $order_and_limit = '';

        if (!empty($analyzed_sql_results['statement']->order)) {
            $order_and_limit .= ' ORDER BY ' . SqlParser\Utils\Query::getClause(
                    $analyzed_sql_results['statement'],
                    $analyzed_sql_results['parser']->list,
                    'ORDER BY'
                );
        }

        if (!empty($analyzed_sql_results['statement']->limit)) {
            $order_and_limit .= ' LIMIT ' . SqlParser\Utils\Query::getClause(
                    $analyzed_sql_results['statement'],
                    $analyzed_sql_results['parser']->list,
                    'LIMIT'
                );
        }

        return 'SELECT ' . implode(', ', $columns) .
        ' FROM ' . implode(', ', $table_references) .
        ' WHERE ' . $where . $order_and_limit;
    }

    /**
     * Transforms a DELETE query into SELECT statement.
     *
     * @param array $analyzed_sql_results Analyzed SQL results from parser.
     *
     * @return string SQL query
     */
    function getSimulatedDeleteQuery($analyzed_sql_results)
    {
        $table_references = SqlParser\Utils\Query::getTables(
            $analyzed_sql_results['statement']
        );

        $where = SqlParser\Utils\Query::getClause(
            $analyzed_sql_results['statement'],
            $analyzed_sql_results['parser']->list,
            'WHERE'
        );

        if (empty($where)) {
            $where = '1';
        }

        $order_and_limit = '';

        if (!empty($analyzed_sql_results['statement']->order)) {
            $order_and_limit .= ' ORDER BY ' . SqlParser\Utils\Query::getClause(
                    $analyzed_sql_results['statement'],
                    $analyzed_sql_results['parser']->list,
                    'ORDER BY'
                );
        }

        if (!empty($analyzed_sql_results['statement']->limit)) {
            $order_and_limit .= ' LIMIT ' . SqlParser\Utils\Query::getClause(
                    $analyzed_sql_results['statement'],
                    $analyzed_sql_results['parser']->list,
                    'LIMIT'
                );
        }

        return 'SELECT * FROM ' . implode(', ', $table_references) .
        ' WHERE ' . $where . $order_and_limit;
    }

    /**
     * Executes the matched_row_query and returns the resultant row count.
     *
     * @param string $matched_row_query SQL query
     *
     * @return integer Number of rows returned
     */
    function executeMatchedRowQuery($matched_row_query)
    {
        $this->dbi->selectDb($GLOBALS['db']);
        // Execute the query.
        $this->result = $this->dbi->tryQuery($matched_row_query);
        // Count the number of rows in the result set.
        $this->result = $this->dbi->numRows($this->result);

        return $this->result;
    }

    /**
     * Handles request for ROLLBACK.
     *
     * @param string $sql_query SQL query(s)
     *
     * @return void
     */
    function handleRollbackRequest($sql_query)
    {
        $sql_delimiter = $_REQUEST['sql_delimiter'];
        $queries = explode($sql_delimiter, $sql_query);
        $this->error = false;
        $error_msg = __(
            'Only INSERT, UPDATE, DELETE and REPLACE '
            . 'SQL queries containing transactional engine tables can be rolled back.'
        );
        foreach ($queries as $sql_query) {
            if (empty($sql_query)) {
                continue;
            }

            // Check each query for ROLLBACK support.
            if (!$this->checkIfRollbackPossible($sql_query)) {
                $global_error = $this->dbi->getError();
                if ($global_error) {
                    $this->error = $global_error;
                } else {
                    $this->error = $error_msg;
                }
                break;
            }
        }

        if ($this->error) {
            unset($_REQUEST['rollback_query']);
            $response = $this->response;
            $message = PMA_Message::rawError($this->error);
            $response->addJSON('message', $message);
            return;
        } else {
            // If everything fine, START a transaction.
            $this->dbi->query('START TRANSACTION');
        }
    }

    /**
     * Checks if ROLLBACK is possible for a SQL query or not.
     *
     * @param string $sql_query SQL query
     *
     * @return bool
     */
    function checkIfRollbackPossible($sql_query)
    {
        $parser = new SqlParser\Parser($sql_query);

        if (empty($parser->statements[0])) {
            return false;
        }

        $statement = $parser->statements[0];

        // Check if query is supported.
        if (!(($statement instanceof SqlParser\Statements\InsertStatement)
            || ($statement instanceof SqlParser\Statements\UpdateStatement)
            || ($statement instanceof SqlParser\Statements\DeleteStatement)
            || ($statement instanceof SqlParser\Statements\ReplaceStatement))
        ) {
            return false;
        }

        // Get table_references from the query.
        $tables = SqlParser\Utils\Query::getTables($statement);

        // Check if each table is 'InnoDB'.
        foreach ($tables as $table) {
            if (!$this->isTableTransactional($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a table is 'InnoDB' or not.
     *
     * @param string $table Table details
     *
     * @return bool
     */
    function isTableTransactional($table)
    {
        $table = explode('.', $table);
        if (count($table) == 2) {
            $db = PMA_Util::unQuote($table[0]);
            $table = PMA_Util::unQuote($table[1]);
        } else {
            $db = $GLOBALS['db'];
            $table = PMA_Util::unQuote($table[0]);
        }

        // Query to check if table exists.
        $check_table_query = 'SELECT * FROM ' . PMA_Util::backquote($db)
            . '.' . PMA_Util::backquote($table) . ' '
            . 'LIMIT 1';

        $this->result = $this->dbi->tryQuery($check_table_query);

        if (!$this->result) {
            return false;
        }

        // List of Transactional Engines.
        $transactional_engines = array(
            'INNODB',
            'FALCON',
            'NDB',
            'INFINIDB',
            'TOKUDB',
            'XTRADB',
            'SEQUENCE',
            'BDB'
        );

        // Query to check if table is 'Transactional'.
        $check_query = 'SELECT `ENGINE` FROM `information_schema`.`tables` '
            . 'WHERE `table_name` = "' . $table . '" '
            . 'AND `table_schema` = "' . $db . '" '
            . 'AND UPPER(`engine`) IN ("'
            . implode('", "', $transactional_engines)
            . '")';

        $this->result = $this->dbi->tryQuery($check_query);

        if ($this->dbi->numRows($this->result) == 1) {
            return true;
        } else {
            return false;
        }
    }
}
