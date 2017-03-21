<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id: Trip.php 13 2013-01-17 23:25:01Z as $
 *
 */

require_once 'CRM/Report/Form.php';
require_once 'CRM/Event/PseudoConstant.php';
require_once 'CRM/Core/OptionGroup.php';
require_once 'CRM/Event/BAO/Participant.php';
require_once 'CRM/Contact/BAO/Contact.php';

class CRM_Report_Form_Event_Trip extends CRM_Report_Form {

    protected $_summary = null;

    protected $_customGroupExtends = array( 'Individual','Participant' );

    /**
     * Array of special statistics to add into the "Count" statitics
     * at the bottom of the report.
     */
    protected $trip_count_statistics = array();

    function __construct( ) {

        static $_events;
        if ( !isset($_events['all']) ) {
            $events = array();
            $query = "
                select id, start_date, title from civicrm_event
                where (is_template IS NULL OR is_template = 0) AND is_active
                order by (date_format(start_date, '%Y')) DESC, title ASC,
                    start_date
            ";
            $dao = CRM_Core_DAO::executeQuery($query);
            while($dao->fetch()) {
               $_events['all'][$dao->id] = CRM_Utils_Date::customFormat(substr($dao->start_date, 0, 10), '%Y') . " - {$dao->title} :: ". CRM_Utils_Date::customFormat(substr($dao->start_date, 0, 10)) . " (#{$dao->id})";
            }
        }

        $this->_columns =
            array(
                  'civicrm_contact' =>
                  array( 'dao'     => 'CRM_Contact_DAO_Contact',
                         'fields'  =>
                         array( 'sort_name_linked' =>
                                array( 'title'     => ts( 'Participant Name' ),
                                       'required'  => true,
                                       'no_repeat' => true,
                                       'dbAlias' => 'sort_name'),
                                'id'  =>
                                array( 'no_display' => true,
                                       'required'   => true, ),
                                'employer_id'       =>
                                array( 'title'     => ts( 'Organization' ), ),
                                'gender_id'       =>
                                array( 'title'     => ts( 'Gender' ),),
                                'age'       =>
                                array( 'title'     => ts( 'Age' ),
                                       'dbAlias'     => '(YEAR(CURDATE()) - YEAR(contact_civireport.birth_date)) - (RIGHT(CURDATE(),5) < RIGHT(contact_civireport.birth_date,5))' ),
                                ),
                         'grouping'  => 'contact-fields',
                         'filters' =>
                         array('sort_name'     =>
                               array( 'title'      => ts( 'Participant Name' ),
                                      'operator'   => 'like' ), ),
                         'order_bys'  =>
                         array( 'sort_name' =>
                                array( 'title' => ts( 'Last Name, First Name'), 'default' => '1', 'default_weight' => '0', 'default_order' => 'ASC'),
                                ),
                         ),

                  'civicrm_email'   =>
                  array( 'dao'     => 'CRM_Core_DAO_Email',
                         'fields'  =>
                         array( 'email' =>
                                array( 'title'     => ts( 'Email' ),
                                       'no_repeat' => true
                                       ),
                                ),
                         'grouping'  => 'contact-fields',
                         'filters' =>
                         array( 'email' =>
                                array( 'title'    => ts( 'Participant E-mail' ),
                                       'operator' => 'like' ) ),
                         ),

                  'civicrm_address'     =>
                  array( 'dao'          => 'CRM_Core_DAO_Address',
                         'fields'       =>
                         array( 'street_address'    => null,
                                'city'              => null,
                                'postal_code'       => null,
                                'state_province_id' =>
                                array( 'title'      => ts( 'State/Province' ), ),
                                'country_id'        =>
                                array( 'title'      => ts( 'Country' ), ),
                                ),
                         'grouping'  => 'contact-fields',
                         ),
                  'civicrm_participant' =>
                  array( 'dao'     => 'CRM_Event_DAO_Participant',
                         'fields'  =>
                         array( 'participant_id'            => array( 'title' => 'Participant ID' ),
                                'participant_record'        => array( 'name'       => 'id' ,
                                                                      'no_display' => true,
                                                                      'required'   => true, ),

                                'event_id'                  => array( 'default' => true,
                                                                      'type'    =>  CRM_Utils_Type::T_STRING ),
                                'status_id'                 => array( 'title'   => ts('Status'),
                                                                      'default' => true ),
                                'role_id'                   => array( 'title'   => ts('Role'),
                                                                      'default' => true ),
                                'participant_fee_level'     => null,

                                'participant_fee_amount'    => null,

                                'participant_register_date' => array( 'title'   => ts('Registration Date') ),
                                ),
                         'grouping' => 'event-fields',
                         'filters'  =>
                         array( 'event_id'                  => array( 'name'         => 'event_id',
                                                                      'title'        => ts( 'Event' ),
                                                                      'operatorType' => CRM_Report_Form::OP_SELECT,
                                                                      'options'      => $_events['all'] ),

                                'sid'                       => array( 'name'         => 'status_id',
                                                                      'title'        => ts( 'Participant Status' ),
                                                                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                                                                      'options'      => CRM_Event_PseudoConstant::participantStatus( null, null, 'label' ) ),
                                'rid'                       => array( 'name'         => 'role_id',
                                                                      'title'        => ts( 'Participant Role' ),
                                                                      'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                                                                      'options'      => CRM_Event_PseudoConstant::participantRole( ) ),
                                'participant_register_date' => array( 'title'        => ' Registration Date',
                                                                      'operatorType' => CRM_Report_Form::OP_DATE ),
                                ),

                         'order_bys'  =>
                         array( 'event_id' =>
                                array( 'title' => ts( 'Event'), 'default_weight' => '1', 'default_order' => 'ASC'),
                                ),
                         ),

                  'civicrm_event' =>
                  array( 'dao'        => 'CRM_Event_DAO_Event',
                         'fields'     =>
                         array(
                               'event_type_id' => array( 'title' => ts('Event Type') ),
                               ),
                         'grouping'  => 'event-fields',
                         'filters'   =>
                         array(
                               'eid' =>  array( 'name'         => 'event_type_id',
                                                'title'        => ts( 'Event Type' ),
                                                'operatorType' => CRM_Report_Form::OP_MULTISELECT,
                                                'options'      => CRM_Core_OptionGroup::values('event_type') ),
                               ),
                         'order_bys'  =>
                         array( 'event_type_id' =>
                                array( 'title' => ts( 'Event Type'), 'default_weight' => '2', 'default_order' => 'ASC'),
                                ),
                         ),


                  );
        $this->_options = array( 'blank_column_begin' => array( 'title'   => ts('Blank column at the Begining'),
                                                                'type'    => 'checkbox' ),

                                 'blank_column_end'   => array( 'title'   => ts('Blank column at the End'),
                                                                'type'    => 'select',
                                                                'options'=> array('' => '-select-',
                                                                                  1   => ts( 'One' ),
                                                                                  2   => ts( 'Two' ),
                                                                                  3   => ts( 'Three' ),
                                                                                  ), ),
                                 );
        parent::__construct( );

        // Remove criteria for "employee data" and "guide position"
        // custom data field groups.
        unset($this->_columns['civicrm_value_guide_data_2']);
        unset($this->_columns['civicrm_value_guide_position_5']);
    }

    function preProcess( ) {
        parent::preProcess( );
    }

    function select( ) {
        $select = array( );
        $this->_columnHeaders = array( );

        //add blank column at the Start
        if ( CRM_Utils_Array::value( 'blank_column_begin', $this->_params['options'] ) ) {
            $select[] = " '' as blankColumnBegin";
            $this->_columnHeaders['blankColumnBegin']['title'] = '_ _ _ _';
        }
        foreach ( $this->_columns as $tableName => $table ) {
            if ( array_key_exists('fields', $table) ) {
                foreach ( $table['fields'] as $fieldName => $field ) {
                    if ( CRM_Utils_Array::value( 'required', $field ) ||
                         CRM_Utils_Array::value( $fieldName, $this->_params['fields'] ) ) {

                        $alias = "{$tableName}_{$fieldName}";
                        $select[] = "{$field['dbAlias']} as $alias";
                        $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] =
                            CRM_Utils_Array::value( 'type', $field );
                        $this->_columnHeaders["{$tableName}_{$fieldName}"]['no_display'] =
                            CRM_Utils_Array::value( 'no_display', $field );
                        $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] =
                            CRM_Utils_Array::value( 'title', $field );
                        $this->_selectAliases[] = $alias;

                    }
                }
            }
        }
        //add blank column at the end
        if ( $blankcols = CRM_Utils_Array::value( 'blank_column_end', $this->_params ) ) {
            for ( $i= 1; $i <= $blankcols; $i++ ) {
                $select[] = " '' as blankColumnEnd_{$i}";
                $this->_columnHeaders["blank_{$i}"]['title'] = "_ _ _ _";
            }
        }

        $this->_select = "SELECT " . implode( ', ', $select ) . " ";
    }

    static function formRule( $fields, $files, $self ) {
        $errors = $grouping = array( );
        return $errors;
    }

    function from( ) {
        $this->_from = "
        FROM civicrm_participant {$this->_aliases['civicrm_participant']}
             LEFT JOIN civicrm_event {$this->_aliases['civicrm_event']}
                    ON ({$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id ) AND
                       ({$this->_aliases['civicrm_event']}.is_template IS NULL OR
                        {$this->_aliases['civicrm_event']}.is_template = 0)
             LEFT JOIN civicrm_contact {$this->_aliases['civicrm_contact']}
                    ON ({$this->_aliases['civicrm_participant']}.contact_id  = {$this->_aliases['civicrm_contact']}.id  )
             {$this->_aclFrom}
             LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
                    ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_address']}.contact_id AND
                       {$this->_aliases['civicrm_address']}.is_primary = 1
             LEFT JOIN  civicrm_email {$this->_aliases['civicrm_email']}
                    ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                       {$this->_aliases['civicrm_email']}.is_primary = 1) ";
    }

    function where( ) {
        $clauses = array( );
        foreach ( $this->_columns as $tableName => $table ) {
            if ( array_key_exists('filters', $table) ) {
                foreach ( $table['filters'] as $fieldName => $field ) {
                    $clause = null;

                    if ( CRM_Utils_Array::value( 'type', $field ) & CRM_Utils_Type::T_DATE ) {
                        $relative = CRM_Utils_Array::value( "{$fieldName}_relative", $this->_params );
                        $from     = CRM_Utils_Array::value( "{$fieldName}_from"    , $this->_params );
                        $to       = CRM_Utils_Array::value( "{$fieldName}_to"      , $this->_params );

                        if ( $relative || $from || $to ) {
                            $clause = $this->dateClause( $field['name'], $relative, $from, $to, $field['type'] );
                        }
                    } else {
                        $op = CRM_Utils_Array::value( "{$fieldName}_op", $this->_params );

                        if ( $fieldName == 'rid' ) {
                            $value =  CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
                            if ( !empty($value) ) {
                                $clause = "( {$field['dbAlias']} REGEXP '[[:<:]]" . implode( '[[:>:]]|[[:<:]]',  $value ) . "[[:>:]]' )";
                            }
                            $op = null;
                        }

                        if ( $op ) {
                            $clause =
                                $this->whereClause( $field,
                                                    $op,
                                                    CRM_Utils_Array::value( "{$fieldName}_value", $this->_params ),
                                                    CRM_Utils_Array::value( "{$fieldName}_min", $this->_params ),
                                                    CRM_Utils_Array::value( "{$fieldName}_max", $this->_params ) );
                        }
                    }

                    if ( ! empty( $clause ) ) {
                        $clauses[] = $clause;
                    }
                }
            }
        }
        if ( empty( $clauses ) ) {
            $this->_where = "WHERE {$this->_aliases['civicrm_participant']}.is_test = 0 ";
        } else {
            $this->_where = "WHERE {$this->_aliases['civicrm_participant']}.is_test = 0 AND " . implode( ' AND ', $clauses );
        }
        if ( $this->_aclWhere ) {
            $this->_where .= " AND {$this->_aclWhere} ";
        }

        // Filter for "participant" role. Participants in all other roles are
        // listed in one of the groups at the top of the report.
        $this->_where .= " AND {$this->_aliases['civicrm_participant']}.role_id = 1 ";

        // Ensure the report has a filter for event_id. Otherwise, the
        // report will try to show for all events, which doesn't work.
        $where_event_id = (int)$this->_params['event_id_value'];
        $this->_where .= " AND $where_event_id ";
    }

    function postProcess( ) {

        // get ready with post process params
        $this->beginPostProcess( );

        // get the acl clauses built before we assemble the query
        $this->buildACLClause( $this->_aliases['civicrm_contact'] );

        $this->trip_build_header_values();

        // build query
        $sql = $this->buildQuery( true );

        // Re-order column headers
        $this->trip_alter_columns();

        // build array of result based on column headers. This method also allows
        // modifying column headers before using it to build result set i.e $rows.
        $this->buildRows ( $sql, $rows );

        // format result set.
        $this->formatDisplay( $rows );

        // assign variables to templates
        $this->doTemplateAssignment( $rows );

        // do print / pdf / instance stuff if needed
        $this->endPostProcess( $rows );
    }

    function alterDisplay( &$rows ) {
        // custom code to alter rows

        $entryFound = false;
        $eventType  = CRM_Core_OptionGroup::values('event_type');

        foreach ( $rows as $rowNum => $row ) {
            // make count columns point to detail report
            // convert display name to links
            if ( array_key_exists('civicrm_participant_event_id', $row) ) {
                if ( $value = $row['civicrm_participant_event_id'] ) {
                    $rows[$rowNum]['civicrm_participant_event_id'] =
                        CRM_Event_PseudoConstant::event( $value, false );
                    $url = CRM_Report_Utils_Report::getNextUrl( 'event/income',
                                                  'reset=1&force=1&id_op=in&id_value='.$value,
                                                                $this->_absoluteUrl, $this->_id );
                    $rows[$rowNum]['civicrm_participant_event_id_link' ] = $url;
                    $rows[$rowNum]['civicrm_participant_event_id_hover'] =
                        ts("View Event Income Details for this Event");
                }
                $entryFound = true;
            }

            // handle event type id
            if ( array_key_exists('civicrm_event_event_type_id', $row) ) {
                if ( $value = $row['civicrm_event_event_type_id'] ) {
                    $rows[$rowNum]['civicrm_event_event_type_id'] = $eventType[$value];
                }
                $entryFound = true;
            }

            // handle participant status id
            if ( array_key_exists('civicrm_participant_status_id', $row) ) {
                if ( $value = $row['civicrm_participant_status_id'] ) {
                    $rows[$rowNum]['civicrm_participant_status_id'] =
                        CRM_Event_PseudoConstant::participantStatus( $value, false , 'label');
                }
                $entryFound = true;
            }

            // handle participant role id
            if ( array_key_exists('civicrm_participant_role_id', $row) ) {
                if ( $value = $row['civicrm_participant_role_id'] ) {
                    $roles = explode( CRM_Core_DAO::VALUE_SEPARATOR, $value );
                    $value = array( );
                    foreach( $roles as $role) {
                        $value[$role] = CRM_Event_PseudoConstant::participantRole( $role, false );
                    }
                    $rows[$rowNum]['civicrm_participant_role_id'] = implode( ', ', $value );
                }
                $entryFound = true;
            }

            // Handel value seperator in Fee Level
            if ( array_key_exists('civicrm_participant_participant_fee_level', $row) ) {
                if ( $value = $row['civicrm_participant_participant_fee_level'] ) {
                    CRM_Event_BAO_Participant::fixEventLevel( $value );
                    $rows[$rowNum]['civicrm_participant_participant_fee_level'] = $value;
                }
                $entryFound = true;
            }

            // Convert display name to link
            if ( ( $displayName = CRM_Utils_Array::value( 'civicrm_contact_sort_name_linked', $row ) ) &&
                 ( $cid         = CRM_Utils_Array::value( 'civicrm_contact_id', $row ) ) ) {
                $url    = CRM_Utils_System::url( 'civicrm/contact/view', "reset=1&cid=$cid" );

                $contactTitle     = ts('View Contact Record');

                $rows[$rowNum]['civicrm_contact_sort_name_linked' ]  = "<a title='$contactTitle' href=$url>$displayName</a>";
                $entryFound = true;
            }

            // Handle country id
            if ( array_key_exists( 'civicrm_address_country_id', $row ) ) {
                if ( $value = $row['civicrm_address_country_id'] ) {
                    $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country( $value, true );
                }
                $entryFound = true;
            }

            // Handle state/province id
            if ( array_key_exists( 'civicrm_address_state_province_id', $row ) ) {
                if ( $value = $row['civicrm_address_state_province_id'] ) {
                    $rows[$rowNum]['civicrm_address_state_province_id'] =
                        CRM_Core_PseudoConstant::stateProvince( $value, true );
                }
                $entryFound = true;
            }

            // Handle employer id
            if ( array_key_exists( 'civicrm_contact_employer_id', $row ) ) {
                if ( $value = $row['civicrm_contact_employer_id'] ) {
                    $rows[$rowNum]['civicrm_contact_employer_id'] = CRM_Contact_BAO_Contact::displayName( $value );
                    $url = CRM_Utils_System::url( 'civicrm/contact/view',
                                                  'reset=1&cid=' . $value, $this->_absoluteUrl );
                    $rows[$rowNum]['civicrm_contact_employer_id_link']  = $url;
                    $rows[$rowNum]['civicrm_contact_employer_id_hover'] =
                        ts('View Contact Summary for this Contact.');
                }
                $entryFound = true;
            }

            // Handle gender
            if ( array_key_exists( 'civicrm_contact_gender_id', $row ) ) {
                if ( $value = $row['civicrm_contact_gender_id'] ) {
                  $genders = CRM_Core_BAO_OptionValue::getOptionValuesAssocArrayFromName('gender');
                  $rows[$rowNum]['civicrm_contact_gender_id'] = $genders[$value];
                }
                $entryFound = true;
            }

            // skip looking further in rows, if first row itself doesn't
            // have the column we need
            if ( !$entryFound ) {
                break;
            }
        }

    }

    function trip_build_header_values() {
      $event_id_array = (array)$this->_params['event_id_value'];
      $event_id = $event_id_array[0];
      if (!$event_id) {
        return;
      }

      // Template will hide some sections if no event_id filter is
      // defined, so here we need to tell it that one is defined.
      $this->assign('has_event_id', true);

      // Build an array of all participant ids; we'll use this below
      // to compile medical and dietary notes for all participants,
      // even when only 50 are displayed on the current page.
      $query = $this->buildQuery(false);
      $ids_query = "select distinct civicrm_participant_participant_record from ($query) as report_query";
      $dao = CRM_Core_DAO::executeQuery($ids_query);
      $participant_ids = array();
      while ($dao->fetch()) {
        $participant_ids[] = $dao->civicrm_participant_participant_record;
      }
      // Only if there are actually participants contained in the report,
      // compile data for participant medical and dietary notes.
      if (!empty($participant_ids)) {
        // Define an array to hold values for each type separately.
        $participant_note_rows = array(
          'medical' => array(),
          'dietary' => array(),
        );

        // Compile medical notes.
        $query = "
          SELECT
            c.sort_name, v.participant_medical_information_27 as value, p.id as participant_id
          FROM
            civicrm_contact c
            INNER JOIN civicrm_participant p ON c.id = p.contact_id
            INNER JOIN civicrm_value_trip_participant_info_3 v ON v.entity_id = p.id
          WHERE
            p.id in (". implode($participant_ids, ',') .")
            AND v.participant_medical_information_27 > ''
          ORDER BY
            c.sort_name
        ";
        $dao = CRM_Core_DAO::executeQuery($query);
        while ($dao->fetch()) {
          $row = array(
            'title' => $dao->sort_name,
            'value' => $dao->value,
          );
          $participant_note_rows['medical'][$dao->participant_id] = $row;
        }

        // Compile dietary notes.
        $query = "
          SELECT
            c.sort_name, v.dietary_info_41 as value, p.id as participant_id
          FROM
            civicrm_contact c
            INNER JOIN civicrm_participant p ON c.id = p.contact_id
            INNER JOIN civicrm_value_trip_participant_info_3 v ON v.entity_id = p.id
          WHERE
            p.id in (". implode($participant_ids, ',') .")
            AND v.dietary_info_41 > ''
          ORDER BY
            c.sort_name
        ";
        $dao = CRM_Core_DAO::executeQuery($query);
        while ($dao->fetch()) {
          $row = array(
            'title' => $dao->sort_name,
            'value' => $dao->value,
          );
          $participant_note_rows['dietary'][$dao->participant_id] = $row;
        }
        $this->assign('participant_note_rows', $participant_note_rows);
      }

      // Use CiviCRM APIs to fetch data about the event, and
      // participants in guide and coordinator roles.
      require_once('api/api.php');

      // Get event data to present at the top of the report.
      $api_params = array(
        'version' => 3,
        'id' => $event_id,
        'debug' => 1,
        'sequential' =>1,
      );
      $result = civicrm_api('event', 'get', $api_params);
      $event_values = $result['values'][0];

      $api_params = array(
        'version' => 3,
        'name' => 'Trip_Data',
        'api.custom_field.get' => 1,
        'sequential' => 1,
      );
      $result = civicrm_api('custom_group', 'get', $api_params);
      if ($result['is_error'] || $result['values'][0]['api.custom_field.get']['is_error']) {
        watchdog('civi_custom', 'Failed getting custom fields for custom group Trip_Data. API error: '. $result['error_message']);
      }
      else {
        $event_custom_fields = array(
          'Code',
          'River',
          'Trip_Notes',
        );

        $api_custom_fields = $result['values'][0]['api.custom_field.get']['values'];
        $i = 0;
        foreach ($api_custom_fields as $custom_field) {
          if (in_array($custom_field['name'], $event_custom_fields)) {
            $custom_key = 'custom_'. $custom_field['id'];
            $event_values[$custom_field['name']] = $event_values[$custom_key];
            $i++;
            if ($i == count($event_custom_fields)) {
              break;
            }
          }
        }

        $duration = round(((strtotime($event_values['end_date']) - strtotime($event_values['start_date'])) / 24 / 60 / 60)) + 1;

        $header_details = array(
          'Trip' => $event_values['title'],
          'Trip code' => $event_values['Code'],
          'Description' => $event_values['event_description'],
          'Start date' => CRM_Utils_Date::customFormat(substr($event_values['start_date'], 0, 10)),
          'End date' => CRM_Utils_Date::customFormat(substr($event_values['end_date'], 0, 10)),
          'Duration' => ($duration < 0 ? 'Error: end date is before start date' : $duration . ' day(s)'),
          'River' => $event_values['River'],
          'Trip notes' => $event_values['Trip_Notes'],
        );
        $this->assign('header_details', $header_details);
      }

      // Also get the data for roles 'guide' (role_id=5) and 'coordinator'(role_id=6).
      // Prime an array to hold rows for each role separately.
      $header_rows = array(
        'guide' => array(),
        'coordinator' => array(),
      );
      // Fetch data for guide role.
      $api_params = array(
        'version' => 3,
        'role_id' => 5, // guide
        'event_id' => $event_id,
        'rowCount' => 100,  // in case there are more than 25 rows, we need to overcome the civicrm api limit.
        'return.custom_38' => 1, // participant position
        'return.custom_41' => 1, // participant dietary
        'return.sort_name' => 1,
        'api.contact.get' => array(
          'return.custom_14' => 1, // first aid
          'return.custom_15' => 1, // first aid expiry
          'return.custom_16' => 1, // utah license
          'return.custom_17' => 1, // utah license expiry
          'return.custom_19' => 1, // cpr expiry
          'return.custom_18' => 1, // food handler expiry
          'return.custom_50' => 1, // individual medical
          'return.custom_51' => 1, // individual dietary
          'is_deleted' => 0,
        ),
      );
      $result = civicrm_api('participant', 'get', $api_params);
      if ($result['is_error'] || $result['values'][0]['api.contact.get']['is_error']) {
        watchdog('civi_custom', 'Failed getting participants in the "guide" role via CiviCRM API. API error: '. $result['error_message']);
      }
      else {
        // If there was no error, store each found contact as a row
        // in the guide rows array.
        foreach ($result['values'] as $value) {
          // Skip this participant record if the contact was not found
          // (which means it's is_deleted).
          if (!$value['api.contact.get']['count']) {
            continue;
          }
          // Merge contact custom fields into $value array for easy reference
          $value = array_merge($value, $value['api.contact.get']['values'][0]);
          // Note: Each array key is used in the template as the column label.
          $row = array(
            'Name' => $value['sort_name'],
            'Position' => $value['custom_38'],
            'Medical' => $value['custom_50'],
            'Dietary' => $value['custom_51'],
          );
          $header_rows['guide'][] = $row;
        }
      }

      // Fetch data for coordinator role.
      // This section will include all roles except participant and guide.
      //
      // Get the roles we want to have in this section.
      $participant_roles = CRM_Event_PseudoConstant::participantRole();
      $skipped_roles = array('Guide', 'Participant');
      $participant_roles = array_diff($participant_roles, $skipped_roles);

      // For each role that should be in this section, get the data
      // on participants for that role.
      foreach ($participant_roles as $rid => $role) {
        $api_params = array(
          'version' => 3,
          'role_id' => $rid,
          'event_id' => $event_id,
          'rowCount' => 100,  // in case there are more than 25 rows, we need to overcome the civicrm api limit.
          'return.custom_27' => 1, // participant medical
          'return.custom_41' => 1, // participant dietary
          'return.sort_name' => 1,
          'api.contact.get' => array(
            'return.custom_14' => 1, // first aid
            'return.custom_15' => 1, // first aid expiry
            'return.custom_16' => 1, // utah license
            'return.custom_17' => 1, // utah license expiry
            'return.custom_19' => 1, // cpr expiry
            'return.custom_18' => 1, // food handler expiry
            'is_deleted' => 0,
          ),
        );
        $result = civicrm_api('participant', 'get', $api_params);
        if ($result['is_error'] || $result['values'][0]['api.contact.get']['is_error']) {
          watchdog('civi_custom', 'Failed getting participants in the "coordinator" role via CiviCRM API. API error: '. $result['error_message']);
        }
        else {
          // If there was no error, store each found contact as a row
          // in the coordinator rows array.
          foreach ($result['values'] as $value) {
            // Skip this participant record if the contact was not found
            // (which means it's is_deleted).
            // Also skip if we've recorded this participant already in
            // another role.
            if (!$value['api.contact.get']['count'] || $header_rows['coordinator'][$value['id']]) {
              continue;
            }

            // Note: Each array key is used in the template as the column label.
            $row = array(
              'Name' => $value['sort_name'],
              'Role' => $role,
              'Medical' => $value['custom_27'],
              'Dietary' => $value['custom_41'],
            );
            $header_rows['coordinator'][$value['id']] = $row;
            $sort[] = "$role {$value['sort_name']}";
          }
        }
      }
      array_multisort($sort, $header_rows['coordinator']);
      $this->assign('header_rows', $header_rows);

      // Finally, build data for extra statistics rows.
      $statistic_rows = array (
        'guides' => array(
          'title' => 'Guides',
          'value' => count($header_rows['guide']),
        ),
        'coordinators' => array(
          'title' => 'Coordinators',
          'value' => count($header_rows['coordinator']),
        ),
        'participants' => array(
          'title' => 'Participants',
          'value' => count($participant_ids),
        ),
        'total' => array(
          'title' => 'Total',
          'value' => count($participant_ids) + count($header_rows['coordinator']) + count($header_rows['guide']),
        ),
      );
      $this->trip_count_statistics = $statistic_rows;


    }

    function countStat( &$statistics, $count ) {
        $statistics['counts'] = $this->trip_count_statistics;

        // Only add the "listed" statistic if it's different from the "participants" one.
        if ($count != $statistics['counts']['participants']['value']) {
          $statistics['counts']['rowCount'] = array(
            'title' => ts('Participant Row(s) Listed'),
             'value' => $count
          );
        }

        return;
    }

    // override this method to build your own statistics
    function statistics( &$rows ) {
        $statistics = array();

        $count = count($rows);

        if ( $this->_rollup && ($this->_rollup != '') && $this->_grandFlag ) {
            $count++;
        }

        $this->countStat  ( $statistics, $count );

        $this->groupByStat( $statistics );

        $this->filterStat ( $statistics );

        // Strip "Event" from filter statistics (we're showing it already
        // in our header stats table).
        foreach ($statistics['filters'] as $id => $filter) {
          if ($filter['title'] == 'Event') {
            unset($statistics['filters'][$id]);
            break;
          }
        }

        return $statistics;
    }

    function trip_alter_columns() {
      if ($this->_columnHeaders['civicrm_value_personal_data_4_custom_29']) {
        // Change the header type to a non-decimal type, so to avoid
        // right-alignment of row data in table cells.
        $this->_columnHeaders['civicrm_value_personal_data_4_custom_29']['type'] = 2;
      }

      if ($this->_columnHeaders['civicrm_value_personal_data_4_custom_29'] && $this->_columnHeaders['civicrm_contact_age']) {

        $weight_header = $this->_columnHeaders['civicrm_value_personal_data_4_custom_29'];
        unset($this->_columnHeaders['civicrm_value_personal_data_4_custom_29']);

        $new_headers = array();
        foreach ($this->_columnHeaders as $key => $header) {
          $new_headers[$key] = $header;
          if ($key == 'civicrm_contact_age') {
            $new_headers['civicrm_value_personal_data_4_custom_29'] = $weight_header;
          }
        }
        $this->_columnHeaders = $new_headers;
      }
    }
}

