<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * set of functions used by tbl_columns_definitions_form.inc.php
 *
 * @package PhpMyAdmin
 */
if (!defined('PHPMYADMIN')) {
    exit;
}

require_once 'libraries/Template.class.php';

/**
 * Function to get html for column attributes
 *
 * @param int        $columnNumber              column number
 * @param array      $columnMeta                column meta
 * @param string     $type_upper                type upper
 * @param int        $length_values_input_size  length values input size
 * @param int        $length                    length
 * @param array|null $extracted_columnspec      extracted column spec
 * @param string     $submit_attribute          submit attribute
 * @param array|null $analyzed_sql              analyzed sql
 * @param array      $comments_map              comments map
 * @param array|null $fields_meta               fields map
 * @param bool       $is_backup                 is backup
 * @param array      $move_columns              move columns
 * @param array      $cfgRelation               configuration relation
 * @param array      $available_mime            available mime
 * @param array      $mime_map                  mime map
 *
 * @return array
 */
function PMA_getHtmlForColumnAttributes(
    $columnNumber, $columnMeta, $type_upper, $length_values_input_size,
    $length, $extracted_columnspec, $submit_attribute, $analyzed_sql,
    $comments_map, $fields_meta, $is_backup, $move_columns, $cfgRelation,
    $available_mime, $mime_map
) {
    // Cell index: If certain fields get left out, the counter shouldn't change.
    $ci = 0;
    // Every time a cell shall be left out the STRG-jumping feature, $ci_offset
    // has to be incremented ($ci_offset++)
    $ci_offset = -1;

    $content_cell = array();

    // column name
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_name')
        ->render(array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null,
            'cfgRelation' => $cfgRelation
        ));
    $ci++;

    // column type
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_type')
        ->render(array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'type_upper' => $type_upper,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null
        ));
    $ci++;

    // column length
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_length')->render(
        array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'length_values_input_size' => $length_values_input_size,
            'length_to_display' => $length
        )
    );
    $ci++;

    // column default
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_default')
        ->render(array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'type_upper' => isset($type_upper) ? $type_upper : null,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null
        ));
    $ci++;

    // column collation
    $tmp_collation = empty($columnMeta['Collation']) ? null : $columnMeta['Collation'];
    $content_cell[$ci] = PMA_generateCharsetDropdownBox(
        PMA_CSDROPDOWN_COLLATION, 'field_collation[' . $columnNumber . ']',
        'field_' . $columnNumber . '_' . ($ci - $ci_offset), $tmp_collation, false
    );
    $ci++;

    // column attribute
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_attribute')
        ->render(array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'extracted_columnspec' => isset($extracted_columnspec) ? $extracted_columnspec : null,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null,
            'submit_attribute' => isset($submit_attribute) ? $submit_attribute : null,
            'analyzed_sql' => isset($analyzed_sql) ? $analyzed_sql : null
        ));
    $ci++;

    // column NULL
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_null')
        ->render(array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null
        ));
    $ci++;

    // column Adjust Privileges
    // Only for 'Edit' Column(s)
    if (isset($_REQUEST['change_column'])
        && ! empty($_REQUEST['change_column'])
    ) {
        $content_cell[$ci] = PMA\Template::get('columns_definitions/column_adjust_privileges')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset
            )
        );
        $ci++;
    }

    // column indexes
    // See my other comment about  this 'if'.
    if (!$is_backup) {
        $content_cell[$ci] = PMA\Template::get('columns_definitions/column_indexes')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'columnMeta' => $columnMeta
            )
        );
        $ci++;
    } // end if ($action ==...)

    // column auto_increment
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_auto_increment')->render(
        array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'columnMeta' => $columnMeta
        )
    );
    $ci++;

    // column comments
    $content_cell[$ci] = PMA\Template::get('columns_definitions/column_comment')->render(
        array(
            'columnNumber' => $columnNumber,
            'ci' => $ci,
            'ci_offset' => $ci_offset,
            'columnMeta' => isset($columnMeta) ? $columnMeta : null,
            'comments_map' => $comments_map
        )
    );
    $ci++;

    // move column
    if (isset($fields_meta)) {
        $current_index = 0;
        for ($mi = 0, $cols = count($move_columns); $mi < $cols; $mi++) {
            if ($move_columns[$mi]->name == $columnMeta['Field']) {
                $current_index = $mi;
                break;
            }
        }
        $content_cell[$ci] = PMA\Template::get('columns_definitions/move_column')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'columnMeta' => $columnMeta,
                'move_columns' => $move_columns,
                'current_index' => $current_index
            )
        );

        $ci++;
    }

    if ($cfgRelation['mimework']
        && $GLOBALS['cfg']['BrowseMIME']
        && $cfgRelation['commwork']
    ) {
        // Column Mime-type
        $content_cell[$ci] = PMA\Template::get('columns_definitions/mime_type')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'available_mime' => $available_mime,
                'columnMeta' => $columnMeta,
                'mime_map' => $mime_map
            )
        );
        $ci++;

        // Column Browser transformation
        $content_cell[$ci] = PMA\Template::get('columns_definitions/transformation')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'available_mime' => $available_mime,
                'columnMeta' => $columnMeta,
                'mime_map' => $mime_map,
                'type' => 'transformation'
            )
        );
        $ci++;

        // column Transformation options
        $content_cell[$ci] = PMA\Template::get('columns_definitions/transformation_option')
            ->render(array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'columnMeta' => $columnMeta,
                'mime_map' => $mime_map,
                'type_prefix' => '',
            ));
        $ci++;

        // Column Input transformation
        $content_cell[$ci] = PMA\Template::get('columns_definitions/transformation')->render(
            array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'available_mime' => $available_mime,
                'columnMeta' => $columnMeta,
                'mime_map' => $mime_map,
                'type' => 'input_transformation'
            )
        );
        $ci++;

        // column Input transformation options
        $content_cell[$ci] = PMA\Template::get('columns_definitions/transformation_option')
            ->render(array(
                'columnNumber' => $columnNumber,
                'ci' => $ci,
                'ci_offset' => $ci_offset,
                'columnMeta' => $columnMeta,
                'mime_map' => $mime_map,
                'type_prefix' => 'input_',
            ));
    }

    return $content_cell;
}
