<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Commonmodel extends CI_Model
{
    private $isAdmin;

    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('session', 'ion_auth', 'commonlib'));
        $this->isAdmin = $this->session->userdata('adminlogin');
    }

    function transaction($args)
    {
//        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? false : $args['begin_trans']) : false;
//        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? false : $args['end_trans']) : false;
//
//        if ($begin_trans) {
//            $this->db->trans_start();
//        }
//
//        if ($end_trans) {
//            $this->db->trans_complete();
//            $this->db->trans_off();
//        }
    }

    function create_table($args)
    {
        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? false : $args['begin_trans']) : false;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? false : $args['end_trans']) : false;
//        $this->transaction(array('begin_trans' => $begin_trans));

        $table = $args["table"];
        $fields = $args["fields"];
        $id = isset($args["id"]) ? (strlen(trim($args["id"])) === 0 ? "id" : $args["id"]) : "id";

        $this->load->dbforge();
        $this->dbforge->add_field($fields);

        $this->dbforge->add_key($id, TRUE);
        $attributes = array('ENGINE' => 'InnoDB');
        $status = $this->dbforge->create_table($table, FALSE, $attributes);

//        $this->transaction(array('end_trans' => $end_trans));
        return $status;
    }

    function modify_table($args)
    {
        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? false : $args['begin_trans']) : false;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? false : $args['end_trans']) : false;
//        $this->transaction(array('begin_trans' => $begin_trans));

        $table = $args["table"];
        $fields = $args["fields"];
        $mode = $args["mode"];

        $this->load->dbforge();
        switch ($mode) {
            case "Add":
                $status = $this->dbforge->add_column($table, $fields);
                break;
            case "Del":
                $status = $this->dbforge->drop_column($table, $fields);
                break;
            case "Edit":
                $status = $this->dbforge->modify_column($table, $fields);
                break;
        }

//        $this->transaction(array('end_trans' => $end_trans));
        return $status;
    }

    function drop_table($table)
    {
        $this->load->dbforge();
        $status = $this->dbforge->drop_table($table, true);

        return $status;
    }

    function create_triggers($table_name)
    {
        $trigger_tau = "tau_$table_name";
        $trigger_tad = "tad_$table_name";
        $audit_table = "aud_$table_name";
        $dbname = getDomain();

        $table_cols = $this->getTablelist(array(
            'sTable' => 'INFORMATION_SCHEMA.COLUMNS',
            'fields' => "column_name",
            'where' => "TABLE_SCHEMA = '$dbname' and TABLE_NAME = '$table_name'"
        ));

        $fields = "`change_type`";
        $values = "";

        if (!empty($table_cols) && count($table_cols) > 0) {
            foreach ($table_cols as $table_col) {
                $fields .= ",`$table_col->column_name`";
                $values .= ",OLD.`$table_col->column_name`";
            }
        }

        $this->db->query("DROP TRIGGER IF EXISTS $trigger_tau");
        $update = "'MOD'" . $values;
        $this->db->query("CREATE TRIGGER $trigger_tau AFTER UPDATE ON `$table_name`
                          FOR EACH ROW BEGIN
                    INSERT INTO `$audit_table` ($fields) VALUES ($update);
            END;"
        );

        $this->db->query("DROP TRIGGER IF EXISTS $trigger_tad");
        $delete = "'DEL'" . $values;
        $this->db->query("CREATE TRIGGER $trigger_tad AFTER DELETE ON `$table_name`
                          FOR EACH ROW BEGIN
                    INSERT INTO `$audit_table` ($fields) VALUES ($delete);
            END;"
        );
    }

    function getTablelist($args)
    {
        $fields = isset($args['fields']) ? $args['fields'] : "";
        $sTable = $args['sTable'];
        $joinlist = isset($args['joinlist']) ? $args['joinlist'] : "";
        $group_by = isset($args['group_by']) ? $args['group_by'] : "";
        $where = isset($args['where']) ? $args['where'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $limit = isset($args['limit']) ? $args['limit'] : 0;
        $offset = isset($args['offset']) ? $args['offset'] : 0;
        $countOrResult = isset($args['countOrResult']) ? (strlen(trim($args['countOrResult'])) === 0 ? "result" : $args['countOrResult']) : "result";

        $showQuery = isset($args['showQuery']) ? $args['showQuery'] : false;
        $showError = isset($args['showError']) ? $args['showError'] : false;

        $filter_where = isset($args['filter_where']) ? $args['filter_where'] : false;
        $filter_prefix = isset($args['filter_prefix']) ? $args['filter_prefix'] : '';
        $do_filter = isset($args['do_filter']) ? $args['do_filter'] : false;
        $filter_condition = isset($args['filter_condition']) ? $args['filter_condition'] : 'or';

        if ($filter_where) {
            $this->load->library('commonlib');
            $filter_where_sql = $this->commonlib->filter_profile_data($filter_prefix, $do_filter);
        }

        if (strlen(trim($fields)) > 0) {
            $this->db->select($fields, false);
        }

        $this->db->from($sTable);

        if (isset($joinlist) && is_array($joinlist)) {
            foreach ($joinlist as $join) {
                $this->db->join($join["table"], $join["condition"], !isset($join["type"]) ? "" : $join["type"]);
            }
        }

        if (strlen(trim($group_by)) > 0) {
            $this->db->group_by($group_by);
        }

        if (is_array($where)) {
            $this->db->where($where);
        } else if (strlen(trim($where)) > 0) {
            $this->db->where($where);
        }

        if ($filter_where) {
            if (strlen(trim($filter_where_sql)) > 0) {
                if ($filter_condition === 'or') {
                    $this->db->or_where($filter_where_sql);
                } else {
                    $this->db->where($filter_where_sql);
                }
            }
        }

        if ($countOrResult === "result") {
            if (strlen(trim($sorting)) > 0) {
                $this->db->order_by($sorting);
            }
            if ($limit > 0) {
                $this->db->limit($limit, $offset);
            }
        }

        $query = $this->db->get();

        if ($showQuery) {
            echo $this->db->last_query();
        }

        $errors = array_filter($this->db->error());
        if ($showError && !empty($errors)) {
            print_r($this->db->error());
        }

        if (!empty($errors)) {
            log_message("debug", $this->db->last_query());
            log_message("debug", json_encode($this->db->error()));
            log_message("error", $this->db->last_query());
            log_message("error", json_encode($this->db->error()));
        }

        if ($countOrResult === "row") {
            return $query->row();
        } else if ($countOrResult === "count") {
            return $query->num_rows();
        } elseif ($countOrResult === "result") {
            return $query->result();
        }
    }

    function getDataByPassingfield($args)
    {
        $field_data = $args['field_data'];
        $table = $args['table'];
        $fields = isset($args['fields']) ? $args['fields'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $override_field = isset($args['override_field']) ? $args['override_field'] : "";
        $rowOrResult = isset($args['rowOrResult']) ? strlen(trim($args['rowOrResult'])) === 0 ? "row" : $args['rowOrResult'] : "row";

        $showQuery = isset($args['showQuery']) ? $args['showQuery'] : false;
        $showError = isset($args['showError']) ? $args['showError'] : false;

        $filter_where = isset($args['filter_where']) ? $args['filter_where'] : false;
        $filter_prefix = isset($args['filter_prefix']) ? $args['filter_prefix'] : '';
        $do_filter = isset($args['do_filter']) ? $args['do_filter'] : false;
        $filter_condition = isset($args['filter_condition']) ? $args['filter_condition'] : 'or';

        if ($filter_where) {
            $this->load->library('commonlib');
            $filter_where_sql = $this->commonlib->filter_profile_data($filter_prefix, $do_filter);
        }

        if (strlen(trim($fields)) > 0) {
            $this->db->select($fields, false);
        }

        $this->db->from($table);

        if (strlen(trim($override_field)) > 0) {
            $this->db->where("$override_field = '$field_data'");
        } else {
            $this->db->where("id = '$field_data'");
        }

        if ($filter_where) {
            if (strlen(trim($filter_where_sql)) > 0) {
                if ($filter_condition === 'or') {
                    $this->db->or_where($filter_where_sql);
                } else {
                    $this->db->where($filter_where_sql);
                }
            }
        }

        if ($rowOrResult === "result") {
            if (strlen(trim($sorting)) > 0) {
                $this->db->order_by($sorting);
            }
        }

        $query = $this->db->get();

        if ($showQuery) {
            echo $this->db->last_query();
        }

        $errors = array_filter($this->db->error());
        if ($showError && !empty($errors)) {
            print_r($this->db->error());
        }

        if (!empty($errors)) {
            log_message("debug", $this->db->last_query());
            log_message("debug", json_encode($this->db->error()));
            log_message("error", $this->db->last_query());
            log_message("error", json_encode($this->db->error()));
        }

        if ($rowOrResult === "row") {
            return $query->row();
        } else if ($rowOrResult === "count") {
            return $query->num_rows();
        } elseif ($rowOrResult === "result") {
            return $query->result();
        }
    }

    function data_change($args)
    {
        $this->load->library("timezonelib");
        $this->timezonelib->getSetTimeZone();

        $mode = $args['mode'];
        $id = isset($args['id']) ? (strlen(trim($args['id'])) === 0 ? "" : $args['id']) : "";
        $table = $args['table'];
        $where = isset($args['where']) ? $args['where'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $data = $args['tableData'];
        $needID = isset($args['needID']) ? (strlen(trim($args['needID'])) === 0 ? "" : "yes") : "";
        $add_user_info = isset($args['add_user_info']) ? (strlen(trim($args['add_user_info'])) === 0 ? "No" : $args['add_user_info']) : "No";

        $showQuery = isset($args['showQuery']) ? $args['showQuery'] : false;
        $showError = isset($args['showError']) ? $args['showError'] : false;

        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? true : $args['begin_trans']) : true;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? true : $args['end_trans']) : true;

//        if ($begin_trans) {
//            $this->db->trans_start();
//        }

        if (strlen(trim($sorting)) > 0) {
            $this->db->order_by($sorting);
        }

        if ($add_user_info === "Yes") {
            $user = "System";
            if (class_exists('ion_auth')) {
                if ($this->ion_auth->logged_in()) {
                    $user = $this->ion_auth->user()->row()->first_name . " " . $this->ion_auth->user()->row()->last_name;
                }
            }
        }

        if ($mode === "Edit") {
            if (is_array($where)) {
                $this->db->where($where);
            } else if (strlen(trim($where)) > 0) {
                $this->db->where($where, NULL, false);
            } else if (strlen(trim($id)) > 0) {
                $this->db->where('id', $id);
            } else {
                return 0;
            }

            if ($add_user_info === "Yes") {
                $data = array_merge($data, array(
                    'modified_by' => $user,
                    'modified_on' => date("Y-m-d H:i:s")
                ));
            }

            $this->db->update($table, $data);
        } else if ($mode === "Add") {
            if ($add_user_info === "Yes") {
                $data = array_merge($data, array(
                    'created_by' => $user,
                    'created_on' => date("Y-m-d H:i:s")
                ));
            }

            $this->db->insert($table, $data);
        } else if ($mode === "Del") {
            $this->db->delete($table, $data);
        }

        if ($showQuery) {
            echo $this->db->last_query();
        }

        $errors = array_filter($this->db->error());

        if ($showError && !empty($errors)) {
            print_r($this->db->error());
        }

        if (!empty($errors) || $this->db->trans_status() === FALSE) {
            log_message("debug", $this->db->last_query());
            log_message("debug", json_encode($this->db->error()));
            log_message("error", $this->db->last_query());
            log_message("error", json_encode($this->db->error()));
            return 0;
        } else {
            if ($mode === "Add" && strlen(trim($needID)) > 0) {
                $return = $this->db->insert_id();
            } else {
                $return = true;
            }
//            if ($end_trans) {
//                $this->db->trans_complete();
//                $this->db->trans_off();
//            }
            return $return;
        }
    }

    function insert_batch($args)
    {
        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? true : $args['begin_trans']) : true;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? true : $args['end_trans']) : true;

//        if ($begin_trans) {
//            $this->db->trans_start();
//        }

        $this->db->insert_batch($args['table'], $args['data']);

//        if ($end_trans) {
//            $this->db->trans_complete();
//            $this->db->trans_off();
//        }

        $errors = array_filter($this->db->error());

        if (!empty($errors) || $this->db->trans_status() === FALSE) {
            log_message("debug", $this->db->last_query());
            log_message("debug", $this->db->error());
            log_message("error", $this->db->last_query());
            log_message("error", $this->db->error());
            return 0;
        } else {
            return true;
        }
    }

    function update_batch($args)
    {
        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? true : $args['begin_trans']) : true;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? true : $args['end_trans']) : true;

//        if ($begin_trans) {
//            $this->db->trans_start();
//        }

        $this->db->update_batch($args['table'], $args['data'], $args['pk']);

//        if ($end_trans) {
//            $this->db->trans_complete();
//            $this->db->trans_off();
//        }

        $errors = array_filter($this->db->error());

        if (!empty($errors) || $this->db->trans_status() === FALSE) {
            log_message("debug", $this->db->last_query());
            log_message("debug", $this->db->error());
            log_message("error", $this->db->last_query());
            log_message("error", $this->db->error());
            return 0;
        } else {
            return true;
        }
    }

    function empty_table($args)
    {
        $tablename = $args["tablename"];
        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? true : $args['begin_trans']) : true;
        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? true : $args['end_trans']) : true;

//        if ($begin_trans) {
//            $this->db->trans_start();
//        }

        $this->db->empty_table($tablename);

//        if ($end_trans) {
//            $this->db->trans_complete();
//            $this->db->trans_off();
//        }

        $errors = array_filter($this->db->error());

        if (!empty($errors) || $this->db->trans_status() === FALSE) {
            log_message("debug", $this->db->last_query());
            log_message("debug", $this->db->error());
            log_message("error", $this->db->last_query());
            log_message("error", $this->db->error());
            return 0;
        } else {
            return true;
        }
    }

    public function update_column_data($update_string, $begin_trans = true, $end_trans = true)
    {
//        if ($begin_trans) {
//            $this->db->trans_start();
//        }

        $this->db->query($update_string);

//        if ($end_trans) {
//            $this->db->trans_complete();
//            $this->db->trans_off();
//        }

        $errors = array_filter($this->db->error());

        if (!empty($errors) || $this->db->trans_status() === FALSE) {
            log_message("debug", $this->db->last_query());
            log_message("debug", $this->db->error());
            log_message("error", $this->db->last_query());
            log_message("error", $this->db->error());
            return 0;
        } else {
            return true;
        }
    }

    function checkCountforgetTablelist($args)
    {
        $args = array_merge($args, array(
            'countOrResult' => "count"
        ));

        $count = $this->getTablelist($args);

        return isset($count) ? strlen(trim($count)) === 0 ? 0 : $count : 0;
    }

    function checkCountforgetDataByPassingfield($args)
    {
        $args = array_merge($args, array(
            'rowOrResult' => "count"
        ));

        $count = $this->getDataByPassingfield($args);

        return isset($count) ? strlen(trim($count)) === 0 ? 0 : $count : 0;
    }

    public function array_search($array, $search_value, $key_to_search)
    {
        foreach ($array as $cur_value) {
            if ($cur_value[$key_to_search] === $search_value) {
                return 1;
            }
        }

        return 0;
    }

    public function array_search_return_key($array, $search_value, $key_to_search)
    {
        foreach ($array as $index => $cur_value) {
            if ($cur_value[$key_to_search] === $search_value) {
                return $index;
            }
        }
    }

    public function array_search_without_key($array, $search_value)
    {
        foreach ($array as $cur_value) {
            if ($cur_value === $search_value) {
                return 1;
            }
        }

        return 0;
    }

    public function array_filter($array, $key, $value)
    {
        $outputArray = array();
        $array_items = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
        foreach ($array_items as $sub) {
            $subArray = $array_items->getSubIterator();
            if (strval($subArray[$key]) === strval($value)) {
                $outputArray[] = iterator_to_array($subArray);
            }
        }
        return $outputArray;
    }

    public function getnotificationlist($args)
    {
        $filtered = $args['filtered'];
        $limit = $args['limit'];
        $ids = $args['ids'];
        $empid = isset($args['empid']) ? $args['empid'] : "";
        $division = $args['division'];
        $region = $args['region'];
        $department = $args['department'];
        $branch = $args['branch'];
        $countOrResult = $args['countOrResult'];

        $this->db->select("n.id nid, n.header, n.content, n.notified_on, n.notified_from, ncd.id, ncd.notification_id, ncd.notified_to, ncd.criteria_id", false);
        $this->db->from("notification n");
        $this->db->join("notification_criteria_data ncd", "ncd.notification_id = n.id", false);

        if ($this->isAdmin === 0) {
            $this->db->or_where("ncd.notified_to", "all_employees");
            if ($filtered === "yes" && count($ids) > 0) {
                $this->db->where_not_in("n.id", $ids);
            }
            $this->db->where("ncd.criteria_id", 0);

            if (strlen(trim($empid)) > 0) {
                $this->db->or_where("ncd.notified_to", "employee");
                $this->db->where("ncd.criteria_id", $empid);
                if ($filtered === "yes" && count($ids) > 0) {
                    $this->db->where_not_in("n.id", $ids);
                }
            }
        }

        if (strlen(trim($division)) > 0) {
            $this->db->or_where("ncd.notified_to", "division");
            $this->db->where("ncd.criteria_id", $division);
            if ($filtered === "yes" && count($ids) > 0) {
                $this->db->where_not_in("n.id", $ids);
            }
        }

        if (strlen(trim($region)) > 0) {
            $this->db->or_where("ncd.notified_to", "region");
            $this->db->where("ncd.criteria_id", $region);
            if ($filtered === "yes" && count($ids) > 0) {
                $this->db->where_not_in("n.id", $ids);
            }
        }

        if (strlen(trim($department)) > 0) {
            $this->db->or_where("ncd.notified_to", "department");
            $this->db->where("ncd.criteria_id", $department);
            if ($filtered === "yes" && count($ids) > 0) {
                $this->db->where_not_in("n.id", $ids);
            }
        }

        if (strlen(trim($branch)) > 0) {
            $this->db->or_where("ncd.notified_to", "branch");
            $this->db->where("ncd.criteria_id", $branch);
            if ($filtered === "yes" && count($ids) > 0) {
                $this->db->where_not_in("n.id", $ids);
            }
        }

        $this->db->order_by("n.id desc");

        if ($countOrResult === "result") {
            if ((int)$limit > 0) {
                $this->db->limit($limit, 0);
            }
        }

        $query = $this->db->get();

        if ($countOrResult === "count") {
            return $query->num_rows();
        } elseif ($countOrResult === "result") {
            return $query->result();
        }
    }

    public function getHolidayList($args)
    {
        $employee = $args['employee'];
        $dashboard = isset($args['dashboard']) ? (strlen(trim($args['dashboard'])) === 0 ? 0 : $args['dashboard']) : 0;

        $args = array_merge($args, array(
            'dashboard' => $dashboard
        ));

        $is_shift_enabled = $this->getTablelist(array(
            'sTable' => "discrete_attribute",
            'fields' => "attribute_value",
            'where' => "attribute_name = 'IS_SHIFT_ENABLED'",
            'countOrResult' => "row"
        ));

        $continue_old_shift = $this->commonmodel->getTablelist(array(
            'fields' => "field_value",
            'sTable' => "variable_config",
            'where' => "field_name = 'continue_old_shift'",
            'countOrResult' => "row"
        ));

        if (count($is_shift_enabled) > 0 && (int)$is_shift_enabled->attribute_value === 1) {
            $this->load->library("timezonelib");
            $this->timezonelib->getSetTimeZoneFromEMPId(array('empid' => $employee));

            if (isset($args['date']) && strlen(trim($args['date'])) > 0) {
                $date = $args['date'];
            } else if (isset($args['current_date']) && strlen(trim($args['current_date'])) > 0) {
                $date = $args['current_date'];
            } else {
                $date = date("Y-m-d");
            }

            $result = $this->getTablelist(array(
                'fields' => "group_concat(sa.holidays) holidays",
                'sTable' => "shift_assign_detail sad",
                'joinlist' => array(
                    array(
                        'table' => "shift_assign sa",
                        'condition' => "sad.shift_assign_id = sa.id",
                        'type' => ""
                    )
                ),
                'where' => "(sad.empid = $employee and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $date . "', '%Y-%m-%d') <= sa.end_date) or
                            (sad.empid = $employee and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and (sa.end_date is null OR sa.end_date =  '0000-00-00'))",
                'countOrResult' => "row"
            ));

            if (count($result) > 0 && (isset($result->holidays) && strlen(trim($result->holidays)) > 0)) {
                $args = array_merge($args, array(
                    'ids' => $result->holidays
                ));
                return $this->collect_holiday_details($args);
            } else {
                $this->load->library("commonlib");
                if ($continue_old_shift->field_value === "Yes") {
                    $result = $this->getTablelist(array(
                        'fields' => "max(start_date) start_date",
                        'sTable' => "shift_assign_detail sad",
                        'joinlist' => array(
                            array(
                                'table' => "shift_assign sa",
                                'condition' => "sad.shift_assign_id = sa.id",
                                'type' => ""
                            )
                        ),
                        'where' => "sad.empid = $employee",
                        'countOrResult' => "row"
                    ));
                    if (count($result) > 0 && (isset($result->start_date) && strlen(trim($result->start_date)) > 0)) {
                        $result_holidays = $this->getTablelist(array(
                            'fields' => "group_concat(sa.holidays) holidays",
                            'sTable' => "shift_assign_detail sad",
                            'joinlist' => array(
                                array(
                                    'table' => "shift_assign sa",
                                    'condition' => "sad.shift_assign_id = sa.id",
                                    'type' => ""
                                )
                            ),
                            'where' => "(sa.holidays is not null and length(sa.holidays) > 0) and sad.empid = $employee and str_to_date('" . $result->start_date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $date . "', '%Y-%m-%d') >= str_to_date('" . $result->start_date . "', '%Y-%m-%d')",
                            'countOrResult' => "row"
                        ));

                        if (count($result_holidays) > 0 && strlen($result_holidays->holidays) > 0) {
                            $args = array_merge($args, array(
                                'ids' => rtrim($result_holidays->holidays, ",")
                            ));
                            return $this->collect_holiday_details($args);
                        } else {
                            return $this->collect_holiday_details($args);
                        }
                    } else {
                        return $this->collect_holiday_details($args);
                    }
                } else {
                    return $this->collect_holiday_details($args);
                }
            }
        } else {
            return $this->collect_holiday_details($args);
        }
    }

    function getDefaultShiftHolidays($args)
    {
        $result = $this->getTablelist(array(
            'fields' => "group_concat(sm.holidays) holidays",
            'sTable' => "shift_master sm",
            'where' => "is_default = 1",
            'countOrResult' => "row"
        ));

        if (count($result) > 0) {
            $args = array_merge($args, array(
                'ids' => $result->holidays
            ));
            return $this->collect_holiday_details($args);
        } else {
            return $this->collect_holiday_details($args);
        }
    }

    function collect_holiday_details($args)
    {
        $division = $args['division'];
        $region = $args['region'];
        $department = $args['department'];
        $branch = $args['branch'];
        $employee = $args['employee'];
        $section = isset($args['section']) ? (strlen(trim($args['section'])) === 0 ? "employee" : $args['section']) : "employee";
        $countOrResult = isset($args['countOrResult']) ? (strlen(trim($args['countOrResult'])) === 0 ? "result" : $args['countOrResult']) : "result";
        $filterforToday = isset($args['filterforToday']) ? (strlen(trim($args['filterforToday'])) === 0 ? "No" : $args['filterforToday']) : "No";
        $current_date = isset($args['current_date']) ? (strlen(trim($args['current_date'])) === 0 ? "" : $args['current_date']) : "";
        $useYear = isset($args['useYear']) ? (strlen(trim($args['useYear'])) === 0 ? "Yes" : $args['useYear']) : "Yes";
        $dashboard = isset($args['dashboard']) ? (strlen(trim($args['dashboard'])) === 0 ? 0 : $args['dashboard']) : 0;
        $ids = isset($args['ids']) ? (strlen(trim($args['ids'])) === 0 ? "" : $args['ids']) : "";

        $exp_date = explode("-", $current_date);
        if (isset($exp_date[1]) && $exp_date[1] != "") {
            $month = $exp_date[1];
        } else {
            $month = date("m");
        }

        if (isset($exp_date[0]) && $exp_date[0] != "") {
            $year = $exp_date[0];
        } else {
            $year = date("Y");
        }

        $roster_holidays = $this->check_if_roster_data_exist_for_date_range($employee, $month, $year);

        $roster_set = "0";
        if ($roster_holidays) {
            $roster_set = "1";
        }

        if (($this->isAdmin === 1 && (int)$dashboard === 1) || (int)$roster_set === 0) {
            //If its admin dashboard
            if ($this->isAdmin === 1 && (int)$dashboard === 1) {
                $this->db->select("distinct holiday, DATE_FORMAT(holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(holidaydate, '%Y-%m-%d') as hldy_date", false);
                $this->db->from("holidaylist h");

                $ids = trim($ids);

                if (strlen($ids) > 0 && substr($ids, 0, 1) !== ",") {
                    if ($section === "employee") {
                        $this->db->where("h.id in (" . $ids . ")");
                    }

                    if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                        $this->db->where("holidaydate", $current_date);
                    } else {
                        $this->db->where("holidaydate >= CURDATE()");
                    }
                } else {
                    $this->db->join("holidaylist_criteria_data hcd", "hcd.holidaylist_id = h.id", false);

                    if ($useYear === "Yes") {
                        $this->db->where('YEAR(holidaydate)', date('Y'), false);
                    }

                    if ($section === "employee") {
                        $this->db->where("hcd.apply_to", "all_employees");
                        $this->db->where("hcd.criteria_id", 0);
                    }

                    if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                        $this->db->where("holidaydate", $current_date);
                    } else {
                        $this->db->where("holidaydate >= CURDATE()");
                    }

                    if (strlen(trim($division)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "division");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate >= CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $division);
                    }

                    if (strlen(trim($region)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "region");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate >= CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $region);
                    }

                    if (strlen(trim($department)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "department");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate >= CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $department);
                    }

                    if (strlen(trim($branch)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "branch");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate >= CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $branch);
                    }

                    if (strlen(trim($employee)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "employee");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate >= CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $employee);
                    }
                    //            $this->db->limit(5, 0);
                }
                $this->db->where("is_roster", "0");
                $this->db->order_by("holidaydate asc");

                $query = $this->db->get();

                if ($countOrResult === "row") {
                    return $query->row();
                } else if ($countOrResult === "count") {
                    return $query->num_rows();
                } elseif ($countOrResult === "result") {
                    return $query->result();
                }
            }

            //If its employees dashboard and roster is not set for the current month
            if (((int)$roster_set === 0 && (int)$dashboard === 1 && $this->isAdmin === 0)) {
                $month_arr = array();
                $flag = 0;
                for ($i = 0; $i <= 6; $i++) {
                    $curr_month = array();
                    if ((int)$flag === 0) {
                        $current_month = date('Y-m-d');
                        $curr_month['first_day'] = date('Y-m-d', strtotime($current_month));
                        $curr_month['last_day'] = date('Y-m-t', strtotime($current_month));
                        $month_arr[] = $curr_month;
                    } else {
                        $next_month = strlen(date('m') + $i) === 1 ? '0' . (date('m') + $i) : date('m') + $i;
                        $curr_month['first_day'] = date("Y-$next_month-01");
                        $curr_month['last_day'] = date('Y-m-t', strtotime($curr_month['first_day']));
                        $month_arr[] = $curr_month;
                    }
                    $flag++;
                }

                //Get top five holidays from coming next 5 months
                $holidays = $this->get_holidays_from_coming_months($month_arr, $employee);

                $hldy = array();
                if (count($holidays) > 0) {
                    //Convert standard class holiday array into array for sorting by date
                    foreach ($holidays as $holiday) {
                        $hldy_array = array();
                        $hldy_array['holiday'] = isset($holiday->holiday) ? $holiday->holiday : "";
                        $hldy_array['hdate'] = isset($holiday->hdate) ? $holiday->hdate : "";
                        $hldy_array['optional'] = isset($holiday->optional) ? $holiday->optional : "";
                        $hldy_array['hldy_date'] = isset($holiday->hldy_date) ? $holiday->hldy_date : "";
                        $hldy[] = $hldy_array;
                    }
                }

                if (count($hldy) > 0) {
                    //Sort the holiday array by its date
                    $holiday_date = array();
                    foreach ($hldy as $key => $row) {
                        $holiday_date[$key] = $row['hldy_date'];
                    }
                    array_multisort($holiday_date, SORT_ASC, $hldy);

                    //After sorting again convert array into standard class object
                    $holiday_so = array();
                    foreach ($hldy as $holiday) {
                        $holiday_so[] = (object)$holiday;

                    }
                    $hldy = $holiday_so;
                }

                return $hldy;
            }

            //If roster is not set then
            if ((int)$roster_set === 0) {
                $this->db->select("distinct holiday, DATE_FORMAT(holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(holidaydate, '%Y-%m-%d') as hldy_date", false);
                $this->db->from("holidaylist h");

                $ids = trim($ids);

                if (strlen($ids) > 0 && substr($ids, 0, 1) !== ",") {
                    if ($section === "employee") {
                        $this->db->where("h.id in (" . $ids . ")");
                    }

                    if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                        $this->db->where("holidaydate", $current_date);
                    } else {
                        $this->db->where("holidaydate > CURDATE()");
                    }
                } else {
                    $this->db->join("holidaylist_criteria_data hcd", "hcd.holidaylist_id = h.id", false);

                    if ($useYear === "Yes") {
                        $this->db->where('YEAR(holidaydate)', date('Y'), false);
                    }

                    if ($section === "employee") {
                        $this->db->where("hcd.apply_to", "all_employees");
                        $this->db->where("hcd.criteria_id", 0);
                    }

                    if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                        $this->db->where("holidaydate", $current_date);
                    } else {
                        $this->db->where("holidaydate > CURDATE()");
                    }

                    if (strlen(trim($division)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "division");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate > CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $division);
                    }

                    if (strlen(trim($region)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "region");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate > CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $region);
                    }

                    if (strlen(trim($department)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "department");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate > CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $department);
                    }

                    if (strlen(trim($branch)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "branch");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate > CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $branch);
                    }

                    if (strlen(trim($employee)) > 0 && $section === "employee") {
                        $this->db->or_where("hcd.apply_to", "employee");
                        if ($filterforToday === "Yes" && strlen(trim($current_date)) > 0) {
                            $this->db->where("holidaydate", $current_date);
                        } else {
                            $this->db->where("holidaydate > CURDATE()");
                        }
                        $this->db->where("hcd.criteria_id", $employee);
                    }
                    //            $this->db->limit(5, 0);
                }
                $this->db->where("is_roster", "0");
                $this->db->order_by("holidaydate asc");

                $query = $this->db->get();

                if ($countOrResult === "row") {
                    return $query->row();
                } else if ($countOrResult === "count") {
                    return $query->num_rows();
                } elseif ($countOrResult === "result") {
                    return $query->result();
                }
            }
        } else {
            if (isset($current_date) && $current_date != "") {
                $start_date = $current_date;
                $end_date = $current_date;
            } else {
                $start_date = $year . "-" . $month . date("-d");
                $last_day = date('t', strtotime($start_date));
                $end_date = $year . "-" . $month . "-" . $last_day;
            }

            $this->db->select("sa.id as shift_assign_id,sa.holidays", false);
            $this->db->from("shift_assign sa");
            $this->db->join("shift_assign_detail sad", "sa.id = sad.shift_assign_id");
            $this->db->where("date_format(sa.start_date, '%Y-%m-%d') between '" . $start_date . "' and '" . $end_date . "'");
            $this->db->where("sad.empid", $employee);

            $query = $this->db->get();
            $results = $query->result();

            if (count($results) > 0) {
                $holidays = array();
                foreach ($results as $result) {
                    $holiday = isset($result->holidays) ? $result->holidays : "";
                    if (isset($holiday) && $holiday !== "") {
                        $this->db->select("distinct hl.holiday, DATE_FORMAT(hl.holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(hl.holidaydate, '%Y-%m-%d') as hldy_date", false);
                        $this->db->from("holidaylist hl");
                        $this->db->join("holidaylist_criteria_data hlcd", "hl.id = hlcd.holidaylist_id");
                        $this->db->where("hlcd.id", $holiday);
                        $this->db->where("hl.is_roster", "1");
                        $this->db->order_by("hl.holidaydate asc");

                        $query = $this->db->get();
                        if ($countOrResult === "row") {
                            return $query->row();
                        } else if ($countOrResult === "count") {
                            return $query->num_rows();
                        } elseif ($countOrResult === "result") {
                            $result = $query->result();
                            if (isset($result) && count($result) > 0) {
                                $holidays[] = $result[0];
                            }
                        }
                    }
                }

                if ($holidays) {
                    return $holidays;
                } else {
                    //Check if its a dashboard page of an employee
                    if ((int)$dashboard === 1 && $this->isAdmin === 0) {
                        //Check if there is any roster set for next month
                        $next_month = strlen(date('m')) === 1 ? '0' . date('m') : date('m');
                        $first_day_next_month = date("Y-$next_month-01");
                        $last_day_next_month = date('Y-m-t', strtotime($first_day_next_month));

                        $this->db->select("sa.id as shift_assign_id,sa.holidays", false);
                        $this->db->from("shift_assign sa");
                        $this->db->join("shift_assign_detail sad", "sa.id = sad.shift_assign_id");
                        $this->db->where("date_format(sa.start_date, '%Y-%m-%d') between '" . $first_day_next_month . "' and '" . $last_day_next_month . "'");
                        $this->db->where("sad.empid", $employee);

                        $query = $this->db->get();
                        $results = $query->result();

                        //If there is any roster set for next month then get the holiday
                        if (count($results) > 0) {
                            $holidays = array();
                            foreach ($results as $result) {
                                $holiday = isset($result->holidays) ? $result->holidays : "";
                                if (isset($holiday) && $holiday !== "") {
                                    $this->db->select("distinct hl.holiday, DATE_FORMAT(hl.holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(hl.holidaydate, '%Y-%m-%d') as hldy_date", false);
                                    $this->db->from("holidaylist hl");
                                    $this->db->join("holidaylist_criteria_data hlcd", "hl.id = hlcd.holidaylist_id");
                                    $this->db->where("hlcd.id", $holiday);
                                    $this->db->where("hl.is_roster", "1");
                                    $this->db->order_by("hl.holidaydate asc");

                                    $query = $this->db->get();
                                    if ($countOrResult === "row") {
                                        return $query->row();
                                    } else if ($countOrResult === "count") {
                                        return $query->num_rows();
                                    } elseif ($countOrResult === "result") {
                                        $result = $query->result();
                                        if (isset($result) && count($result) > 0) {
                                            $holidays[] = $result[0];
                                        }
                                    }
                                }
                            }
                            if ($holidays) {
                                return $holidays;
                            }
                        } else {
                            //If no roster set for next month then get the next 5 holidays after next to next month
                            $curr_month = date('Y-m-d');
                            $where = "hl.holidaydate >= '$curr_month' AND (hlcd.apply_to = 'employee' AND criteria_id = '$employee' OR hlcd.apply_to = 'department' AND criteria_id = '$department' OR hlcd.apply_to = 'region' AND criteria_id = '$region' OR  hlcd.apply_to = 'branch' AND criteria_id = '$branch' OR hlcd.apply_to = 'all_employees')";

                            $holidays = array();
                            $this->db->select("distinct hl.holiday, DATE_FORMAT(hl.holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(hl.holidaydate, '%Y-%m-%d') as hldy_date", false);
                            $this->db->from("holidaylist hl");
                            $this->db->join("holidaylist_criteria_data hlcd", "hl.id = hlcd.holidaylist_id");
                            $this->db->where($where);
                            $this->db->order_by("hl.holidaydate asc");
                            $this->db->limit(5, 0);

                            $query = $this->db->get();
                            if ($countOrResult === "row") {
                                return $query->row();
                            } else if ($countOrResult === "count") {
                                return $query->num_rows();
                            } elseif ($countOrResult === "result") {
                                $result = $query->result();
                                if (isset($result) && count($result) > 0) {
                                    $holidays = $result;
                                }
                            }

                            if ($holidays) {
                                return $holidays;
                            }
                        }
                    }
                }
            }
        }


    }

    public function getLeaveCalendarDetails($args)
    {
        $view = $args['view'];
        $calendarFilter = $args['calendarFilter'];
        $empList = $args['empList'];

        $this->db->select("la.startdate, la.enddate, concat_ws(' ', firstname, lastname) ename, lt.leavetype,
                        concat_ws(' ', la.totaldays, IF(la.totaldays <= 1, 'day', 'days')) as totaldays,
                        la.status, lt.legend, la.applied_status", false);
        $this->db->from("leaves_applied la");
        $this->db->join("leave_type lt", "la.leavetypeid = lt.id");
        $this->db->join("emp e", "la.empid = e.id");
        $this->db->where("la.transtype", "DR");

        if ($args['showcurrentLeave'] === 'Yes') {
            $this->db->where("la.status <= ", 2, false);
        } else {
            $this->db->where("la.status = ", 1, false);
        }

        if ($view === "month-view" && strlen(trim($calendarFilter)) > 0) {
            $this->db->where("DATE_FORMAT(la.startdate, '%c') = ", $calendarFilter, false);
        }

        if ($args['calendarGrouping'] === 'manager') {
            $this->db->where("manager = ", $args['calendarGroupingData'], false);
        } else if (isset($empList)) {
            $this->db->where_in("empid", $empList, false);
        }

        $query = $this->db->get();

        return $query->result();
    }

    public function getLeaveDetails($args)
    {
        $empid = $args['empid'];

        $this->db->select("el.*, lt.*", false);
        $this->db->from("emp_leaves el");
        $this->db->join("leave_type lt", "el.type = lt.id");
        $this->db->where("lt.status = 'Active'");

        if ($empid > 0) {
            $this->db->where("el.empid = ", $empid, false);
        }

        $query = $this->db->get();

        return $query->result();

    }

    /*
     * It gets the leave status text based on leave code.
     */
    public function getLeaveStatus($leavecode)
    {
        $leavestatus = array("Pending", "Approved", "In Progress", "Rejected");
        return $leavestatus[$leavecode];
    }

    /*
     * @author: deepak
     */
    public function getPivotColumn($table, $column, $where = "", $separator = ",")
    {
        $args = array(
            'sTable' => $table,
            'fields' => $column
        );
        if ($where !== "") {
            $args["where"] = $where;
        }

        $list = $this->getTablelist($args);
        $columnValues = "";
        foreach ($list as $record) {
            $columnValues = $columnValues . $record->$column . $separator;
        }
        $columnValues = substr($columnValues, 0, strlen($columnValues) - 1);

        return $columnValues;
    }

    function getDiscreteList($discreteAttribute)
    {
        $this->db->select('id,attribute_value, number_in_sequence');
        $this->db->where(array('attribute_name' => $discreteAttribute));
        $this->db->order_by('number_in_sequence asc');
        $query = $this->db->get('discrete_attribute');
        return $query->result();
    }

    /*
     * @author: deepak
     *
     * days_in_month($month, $year)
     * Returns the number of days in a given month and year, taking into account leap years.
     *
     * $month: numeric month (integers 1-12)
     * $year: numeric year (any integer)
     *
     */
    function days_in_month($month, $year)
    {
        // calculate number of days in a month
        return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
    }

    function days_in_date_range($start, $end)
    {
        $date1 = date_create($start);
        $date2 = date_create($end);
        $interval = date_diff($date1, $date2);
        return $interval->format('%a') + 1;
    }

    public function AddEmployeeTraining($args)
    {
        $empid = $args['empid'];

        if (strlen(trim($empid)) > 0) {
            $data_args = array(
                'field_data' => $empid,
                'table' => "emp",
                'fields' => "division"
            );
            $division = ($this->checkCountforgetDataByPassingfield($data_args) > 0) ? $this->getDataByPassingfield($data_args)->division : "";

            $data_args = array(
                'field_data' => $empid,
                'table' => "emp",
                'fields' => "region"
            );
            $region = ($this->checkCountforgetDataByPassingfield($data_args) > 0) ? $this->getDataByPassingfield($data_args)->region : "";

            $data_args = array(
                'field_data' => $empid,
                'table' => "emp",
                'fields' => "department"
            );
            $department = ($this->checkCountforgetDataByPassingfield($data_args) > 0) ? $this->getDataByPassingfield($data_args)->department : "";

            $this->db->select("*");
            $this->db->from("training_criteria_data");

            $this->db->where("apply_to", "All");
            $this->db->where("criteria_id", 0);

            $this->db->or_where("apply_to", "Employee");
            $this->db->where("criteria_id", $empid);


            if (strlen(trim($division)) > 0) {
                $this->db->or_where("apply_to", "Division");
                $this->db->where("criteria_id", $division);
            }

            if (strlen(trim($region)) > 0) {
                $this->db->or_where("apply_to", "Region");
                $this->db->where("criteria_id", $region);
            }

            if (strlen(trim($department)) > 0) {
                $this->db->or_where("apply_to", "Department");
                $this->db->where("criteria_id", $department);
            }

            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                $list = $query->result();

                if (is_array($list)) {
                    foreach ($list as $row) {
                        $count = $this->checkCountforgetTablelist(array(
                            'sTable' => "training_employees",
                            'where' => "training_id = " . $row->training_id . " and empid = " . $empid
                        ));

                        if ($count === 0) {
                            $data = array();
                            $data = array(
                                'mode' => "Add",
                                'table' => "training_employees",
                                'tableData' => array(
                                    'training_id' => $row->training_id,
                                    'empid' => $empid,
                                    'status' => "Pending"
                                )
                            );
                            $this->data_change($data);
                        }
                    }
                }
            }
        }
    }

    function getEmployeeList($args)
    {
        $group = strtolower($args['apply_to']);
        $crieria_id = $args['crieria_id'];

        if ($group === "division" || $group === "department" || $group === "region" || $group === "branch") {
            $data_args = array(
                'fields' => "e.id",
                'where' => $group . " = " . $crieria_id . " and e.status = 'Active'"
            );
            $GroupingData = $this->getActiveEmployeeTableList($data_args);
        } else if ($group === "employee") {
            $GroupingData = array((object)array(
                'id' => $crieria_id,
                'where' => "e.status = 'Active'"
            ));
        } else if ($group === "all") {
            $data_args = array(
                'fields' => "e.id",
                'where' => "e.status = 'Active'"
            );
            $GroupingData = $this->getActiveEmployeeTableList($data_args);
        }

        return $GroupingData;
    }

    public static function getQuarterByMonth($monthNumber)
    {
        return floor(($monthNumber - 1) / 3) + 1;
    }

    public static function getHalfYearByMonth($monthNumber)
    {
        return ($monthNumber <= 6) ? "1st" : "2nd";
    }

    public static function getHalfMonthByDays($dayNumber)
    {
        return ($dayNumber <= 15) ? "1st" : "2nd";
    }

    public function determineDayValue($args)
    {
        $this->load->library("timezonelib");
        $this->timezonelib->getSetTimeZone();
        $last_value = "";
        try {
            if ($args['day'] === date("d")) {
                $last_value = date($args['day'] . '-m-Y');
            } else {
                $last_value = "";
            }

            return $last_value;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function determineCurrentValue($args)
    {
        $this->load->library("timezonelib");
        $this->timezonelib->getSetTimeZone();
        $last_value = "";
        try {
            switch ($args['basis']) {
                case "Hourly":
                    $last_value = date('H-Y');
                    break;
                case "Daily":
                    $last_value = date('d-Y');
                    break;
                case "Weekly":
                    $last_value = date('W-Y');
                    break;
                case "Bi Monthly":
                case "Half Monthly":
                    $last_value = $this->getHalfMonthByDays(date('d')) . date('-m-Y');
                    break;
                case "Monthly":
                    $last_value = date('m-Y');
                    break;
                case "Quarterly":
                    $last_value = $this->getQuarterByMonth(date('m')) . "-" . date('Y');
                    break;
                case "Half Yearly":
                    $last_value = $this->getHalfYearByMonth(date('m')) . "-" . date('Y');
                    break;
                case "Yearly":
                    $last_value = date('Y-Y');
                    break;
            }

            return $last_value;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function determinePreviousValue($args)
    {
        $this->load->library("timezonelib");
        $this->timezonelib->getSetTimeZone();
        $last_value = "";
        try {
            switch ($args['basis']) {
                case "Hourly":
                    $last_value = date("H-Y", strtotime('-1 hour', strtotime(date("d-m-Y H:i"))));
                    break;
                case "Daily":
                    $last_value = date("d-Y", strtotime('-1 day', strtotime(date("d-m-Y"))));
                    break;
                case "Weekly":
                    $last_value = date("W-Y", strtotime('-1 week', strtotime(date("d-m-Y"))));
                    break;
                case "Bi Monthly":
                case "Half Monthly":
                    if (date('d') <= 15) {
                        $last_value = $this->getHalfMonthByDays(date('d', strtotime("last day of last month"))) . date('-m-Y', strtotime("last day of last month"));
                    } else {
                        $last_value = $this->getHalfMonthByDays(date("15")) . date("-m-Y");
                    }
                    break;
                case "Monthly":
                    $last_value = date("m-Y", strtotime('-1 month', strtotime(date("d-m-Y"))));
                    break;
                case "Quarterly":
                    $last_value = $this->getQuarterByMonth(date("m", strtotime('-3 month', strtotime(date("d-m-Y"))))) . "-" . date("Y", strtotime('-3 month', strtotime(date("d-m-Y"))));
                    break;
                case "Half Yearly":
                    $last_value = $this->getHalfYearByMonth(date("m", strtotime('-6 month', strtotime(date("d-m-Y"))))) . "-" . date("Y", strtotime('-6 month', strtotime(date("d-m-Y"))));
                    break;
                case "Yearly":
                    $last_value = date("Y-Y", strtotime('-12 month', strtotime(date("d-m-Y"))));
                    break;
            }

            return $last_value;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getMonth($args)
    {
        $Abbr = $args['abbr'];
        $month = $args['month'];
        $monthArrayFull = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
        $monthArrayAbbr = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');

        if (strlen(trim($month)) === 0) {

            if (strlen(trim($Abbr)) === 0) {
                return $monthArrayFull[$month];
            } else {
                return $monthArrayAbbr[$month];
            }

        } else {
            if (strlen(trim($Abbr)) === 0) {
                return $monthArrayFull;
            } else {
                return $monthArrayAbbr;
            }
        }
    }

    public function AddLeavesForEmployee()
    {
        try {
            // collect all the employees which don't have leaves at their disposal
            $this->db->select("e.*", false);
            $this->db->from("emp e");
            $this->db->where("status = 'Active' and current_date() >= dateofjoin");

            $query = $this->db->get();

            // check the count and then go inside if  > 0
            if ($query->num_rows() > 0) {

                // loop through the employee list
                foreach ($query->result() as $row) {

                    echo "\n====>Employee ID: " . $row->id;
                    echo "\n====>Employee Name: " . $row->firstname . " " . $row->lastname;

                    // get the employee id
                    $empid = $row->id;

                    // get doj
                    $doj = $row->dateofjoin;

                    // get the details like branch, region, employment status, probation status
                    echo "\n====>" . "collecting the details like branch, region, employment status, probation status";
                    $branch = strlen(trim($row->branch)) > 0 ? $row->branch : 0;
                    $region = strlen(trim($row->region)) > 0 ? $row->region : 0;
                    $job_status = strlen(trim($row->job_status)) ? $row->job_status : 0;
                    $probation_status = $row->probation_status;

                    // check if doj is there or not and if not then add to reminders variable
                    // and if yes then go inside for further calculations
                    if (strlen(trim($doj)) > 0) {
                        echo "\n====>" . "found doj";
                        // create where condition based on details
                        $where = "prorata = 'No' and status = 'Active' and (branch_id = 0 or FIND_IN_SET(" . $branch . ", branch_id) > 0)";
                        $where = $where . " and (employment_type = 0 or FIND_IN_SET(" . $job_status . ", employment_type) > 0) ";
                        $where = $where . " and (region_id = 0 or FIND_IN_SET(" . $region . ", region_id) > 0) ";

                        // query to leave type table
                        $this->db->from("leave_type");
                        $this->db->where($where);

                        $leave_query = $this->db->get();

                        echo "\n====>leave count : " . count($leave_query);

                        // check the count and then go inside if  > 0
                        if ($leave_query->num_rows() > 0) {

                            // loop through the leave list
                            foreach ($leave_query->result() as $leaveRow) {
                                echo "\n====> Check if " . $leaveRow->leavetype . " leave is auto credit";
                                if ((int)$this->is_leave_type_auto_credit($leaveRow->id) === 1) {
                                    echo "\n====> Check if leave type applicable to employee";
                                    //check if leave type is applicable to employee
                                    if ((int)$this->leave_applicable_to_emp($empid, $leaveRow->id) === 1) {
                                        // check if employee is on probation or not and determine balance leave accordingly
                                        echo "\n====> employee status : " . $probation_status;

                                        if ($leaveRow->is_lwp === "Yes") {
                                            if ($probation_status === "Probation" && $leaveRow->applicable_to_employees_on_probation === "No") {
                                                echo "\n====>Cannot assign LWP Leave as employee is on probation and setting is set to NO";
                                            } else {
                                                $count = $this->checkCountforgetTablelist(array(
                                                    'sTable' => "emp_leaves",
                                                    'where' => array(
                                                        'empid' => $empid,
                                                        'type' => $leaveRow->id
                                                    )
                                                ));

                                                if ((int)$count === 0) {
                                                    $args = array(
                                                        'mode' => 'Add',
                                                        'table' => 'emp_leaves',
                                                        'tableData' => array(
                                                            'empid' => $empid,
                                                            'type' => $leaveRow->id,
                                                            'balance' => 0,
                                                            'opening_balance' => 0,
                                                            'total_balance' => 0
                                                        )
                                                    );

                                                    $this->data_change($args);

                                                    $this->AddLeaveStatus(array(
                                                        'empid' => $empid,
                                                        'leave_id' => $leaveRow->id,
                                                        'status' => "CR",
                                                        'balance' => 0
                                                    ));

                                                    $args = array(
                                                        'mode' => 'Add',
                                                        'table' => 'leave_activity_log',
                                                        'tableData' => array(
                                                            'empid' => $empid,
                                                            'activity_type' => 0 . " day(s) " . $leaveRow->leavetype . " leave",
                                                            'activity_date' => date("Y-m-d"),
                                                            'description' => "has been credited for the first time."
                                                        )
                                                    );

                                                    $this->data_change($args);

                                                    echo "\n====>Added LWP Leave";
                                                }
                                            }
                                        } else {
                                            if ($probation_status === "Probation") {
                                                if ($leaveRow->applicable_to_employees_on_probation === "No") {
                                                    $balance = 0;
                                                } else {
                                                    if ($leaveRow->prorata === "Yes") {
                                                        $balance = 0;
                                                    } else {

                                                        $balanceargs = array(
                                                            'applicable_from' => $leaveRow->applicable_from,
                                                            'doj' => $doj,
                                                            'amt' => $leaveRow->amt
                                                        );

                                                        echo "\n====>" . "calculating balance for the year for employee on probation";
                                                        $balance = $this->getLeaveAmountforFinancialYear($balanceargs);
                                                    }
                                                }
                                            } else {
                                                if ($leaveRow->prorata === "Yes") {
                                                    $balance = 0;
                                                } else {
                                                    $balanceargs = array(
                                                        'applicable_from' => $leaveRow->applicable_from,
                                                        'doj' => $doj,
                                                        'amt' => $leaveRow->amt
                                                    );

                                                    echo "\n====>" . "calculating balance for the year for normal employee";
                                                    $balance = $this->getLeaveAmountforFinancialYear($balanceargs);
                                                }
                                            }

                                            echo "\n====>balance = " . $balance;

                                            // get the count for that leave type and for selected employee
                                            // if count is 0 then add otherwise leave it
                                            $count = $this->checkCountforgetTablelist(array(
                                                'sTable' => "emp_leaves",
                                                'where' => array(
                                                    'empid' => $empid,
                                                    'type' => $leaveRow->id
                                                )
                                            ));

                                            if (((int)$count === 0 && $probation_status !== "Probation") || ((int)$count === 0 && $probation_status === "Probation" && $leaveRow->applicable_to_employees_on_probation === "Yes")) {
                                                // add data to emp leaves table
                                                $args = array();
                                                $args = array(
                                                    'mode' => 'Add',
                                                    'table' => 'emp_leaves',
                                                    'tableData' => array(
                                                        'empid' => $empid,
                                                        'type' => $leaveRow->id,
                                                        'balance' => $balance,
                                                        'opening_balance' => $balance,
                                                        'total_balance' => $balance
                                                    )
                                                );

                                                $this->data_change($args);

                                                $this->AddLeaveStatus(array(
                                                    'empid' => $empid,
                                                    'leave_id' => $leaveRow->id,
                                                    'status' => "CR",
                                                    'balance' => $balance
                                                ));

                                                echo "\n====>" . "Leaves added for emp id " . $empid;

                                                // add to log leaves log
                                                $args = array();
                                                $args = array(
                                                    'mode' => 'Add',
                                                    'table' => 'leave_activity_log',
                                                    'tableData' => array(
                                                        'empid' => $empid,
                                                        'activity_type' => $balance . " day(s) " . $leaveRow->leavetype . " leave",
                                                        'activity_date' => date("Y-m-d"),
                                                        'description' => "has been credited for the first time."
                                                    )
                                                );

                                                $this->data_change($args);
                                            } else {
                                                if ((int)$count > 0) {
                                                    echo "\n====>" . $leaveRow->leavetype . " Leave already added for " . $row->firstname . " " . $row->lastname;
                                                } else if ($leaveRow->applicable_to_employees_on_probation === "No") {
                                                    echo "\n====>" . $leaveRow->leavetype . " Leave not applicable to " . $row->firstname . " " . $row->lastname . "as applicability set to No";
                                                }
                                            }
                                        }
                                    } else {
                                        echo "\n====> " . "Leave is not applicable to " . $row->firstname . " " . $row->lastname;
                                    }
                                } else {
                                    echo "\n====> " . $leaveRow->leavetype . " leave is set as auto credit NO";
                                }
                            }
                        } else {
                            echo "\n====>" . "No leaves found, exiting.";
                        }
                    } else {
                        echo "\n====>" . "No doj found, exiting.";
                    }
                }
            }
        } catch (Exception $e) {
            return array();
        }
    }

    public function sendEmailToAdminForMissingLeaveData()
    {

        try {
            // declare variable for sending email reminders to admin
            $reminder = array();

            // collect all the employees which don't have leaves at their disposal
            $this->db->select("e.*", false);
            $this->db->from("emp e");
            $this->db->where("status = 'Active'");

            $query = $this->db->get();

            // check the count and then go inside if  > 0
            if ($query->num_rows() > 0) {

                // loop through the employee list
                foreach ($query->result() as $row) {

                    // get doj
                    $doj = $row->dateofjoin;

                    // get the details like branch, employment status
                    $branch = strlen(trim($row->branch)) > 0 ? $row->branch : 0;
                    $region = strlen(trim($row->region)) > 0 ? $row->region : 0;
                    $job_status = strlen(trim($row->job_status)) ? $row->job_status : 0;

                    // create where condition based on details
                    $where = "status = 'Active' and (branch_id = 0 or FIND_IN_SET(" . $branch . ", branch_id) > 0)";
                    $where = $where . " and (employment_type = 0 or FIND_IN_SET(" . $job_status . ", employment_type) > 0) ";
                    $where = $where . " and (region_id = 0 or FIND_IN_SET(" . $region . ", region_id) > 0) ";
                    $where = $where . " and is_lwp = 'No'";

                    // query to leave type table
                    $this->db->from("leave_type");
                    $this->db->where($where);

                    $leave_query = $this->db->get();

                    // check the count if employee is eligible or not for leave and then go inside if  > 0
                    if ((int)$leave_query->num_rows() > 0 && strlen(trim($doj)) === 0) {
                        // set the data for reminders
                        $reminder = array_merge($reminder, array(array(
                            'emp' => $row->firstname . " " . $row->lastname,
                            'code' => $row->empno,
                            'DOJ' => "Missing",
                            'branch' => ((int)$branch > 0) ? "Added" : "Missing",
                            'region' => ((int)$region > 0) ? "Added" : "Missing",
                            'employment_type' => ((int)$job_status > 0) ? "Added" : "Missing",
                        )));
                    }

                }

                if (count($reminder) > 0) {
                    return $reminder;
                } else {
                    return array();
                }
            }
        } catch (Exception $e) {
            return array();
        }
    }

    public function getLeaveAmountforFinancialYear($args)
    {
        // get the required variables
        $applicable_from = $args['applicable_from'];
        $dateofJoin = $args['doj'];
        $amt = $args['amt'];
        $type = isset($args['type']) ? strlen(trim($args['type'])) === 0 ? "" : $args['type'] : "";

        // calculate leave amount in days
        $leave_per_day = (float)$amt / 365;

        // calculate start date of the current year
        $start_date = date('Y-m-d', strtotime(date('Y') . "-" . ((int)$applicable_from + 1) . "-01"));

        // calculate end date of the current year
        $end_date = date('Y-m-d', strtotime("+12 months", strtotime($start_date)));
        $end_date = date('Y-m-d', strtotime("-1 day", strtotime($end_date)));

        // get the date of join for employee
        $doj_date = date('Y-m-d', strtotime($dateofJoin));

        // get the current
        $current_date = date('Y-m-d');

        //calculate the difference in days
        if ($start_date > $current_date) {
            $end_date = date('Y-m-d', strtotime("-1 year", strtotime($end_date)));
            $diff = floor((strtotime($end_date) - strtotime($doj_date)) / 86400) + 1;
        } else if ($doj_date > $current_date) {
            $diff = -1;
        } else if ($end_date > $current_date) {
            if ($type === "prorata") {
                $diff = floor((strtotime($current_date) - strtotime($doj_date)) / 86400) + 1;
            } else {
                $diff = floor((strtotime($end_date) - strtotime($doj_date)) / 86400) + 1;
            }
        } else {
            $diff = floor((strtotime($end_date) - strtotime($doj_date)) / 86400) + 1;
        }

        echo "\n====> Difference in number of days = " . $diff;

        // check the difference, if > 0 then calculate total leave to apply
        if ((int)$diff > 0) {
            return number_format((float)((float)$leave_per_day * (int)$diff), 2);
        } else if ((int)$diff < 0) {
            return $diff;
        } else {
            return 0;
        }
    }

    public function getAdminEmployeeEmailDetails()
    {
        return $this->getTablelist(array(
            'sTable' => "emp e",
            'fields' => "e.id, e.office_email, concat(e.empno, '-', e.firstname, ' ', e.lastname) as ename",
            'joinlist' => array(
                array(
                    'table' => "users u",
                    'condition' => "e.id = u.empid",
                    'type' => ""
                ),
                array(
                    'table' => "users_groups ug",
                    'condition' => "u.id = ug.user_id",
                    'type' => ""
                )
            ),
            'where' => "ug.group_id = 1"
        ));
    }

    public function checkIfEmployeeIsEligibleForLeave($args)
    {

        $balance = 0;
        $eligible = "No";
        $empid = $args['empid'];
        $leave_id = $args['leave_id'];
        $need_balance = isset($args['need_balance']) ? strlen(trim($args['need_balance'])) > 0 ? $args['need_balance'] : "No" : "No";
        $prorate = isset($args['basis']) ? strlen(trim($args['basis'])) > 0 ? $args['basis'] : "" : "";
        $current_value = isset($args['current_value']) ? strlen(trim($args['current_value'])) > 0 ? $args['current_value'] : "" : "";

        // collect all the employees which don't have leaves at their disposal
        $empArgs = array(
            'sTable' => "emp",
            'countOrResult' => "row",
            'where' => "status = 'Active' and id = " . $empid
        );

        $query = $this->getTablelist($empArgs);

        // check the count and then go inside if  > 0
        if (count($query) > 0) {

            // get doj
            echo "\n====> doj = " . $doj = $query->dateofjoin;

            // get the details like branch, region, employment status, probation status
            $branch = strlen(trim($query->branch)) > 0 ? $query->branch : 0;
            $region = strlen(trim($query->region)) > 0 ? $query->region : 0;
            $job_status = $query->job_status;
            $probation_status = $query->probation_status;

            echo "\n====> prorate = " . $prorate;
            echo "\n====> string = " . $current_value;


            if ($prorate !== "Bi Monthly" && strlen(trim($doj)) > 0) {
                echo "\n====> inside others ";

                // create where condition based on details
                $where = "id = " . $leave_id . " and status = 'Active' and (branch_id = 0 or FIND_IN_SET(" . $branch . ", branch_id) > 0)";
                $where = $where . " and (employment_type = 0 or FIND_IN_SET(" . $job_status . ", employment_type) > 0) ";
                $where = $where . " and (region_id = 0 or FIND_IN_SET(" . $region . ", region_id) > 0) ";

                // query to leave type table
                $leaveArgs = array(
                    'sTable' => "leave_type",
                    'countOrResult' => "row",
                    'where' => $where
                );

                $leave_query = $this->getTablelist($leaveArgs);

                if (count($leave_query) > 0) {
                    // check if employee is on probation or not and determine balance leave accordingly
                    if ($probation_status === "Probation") {
                        if ($leave_query->applicable_to_employees_on_probation === "No") {
                            if ($need_balance === "Yes") {
                                $balance = 0;
                            } else {
                                $eligible = "No";
                            }
                        } else {
                            if ($leave_query->prorata === "Yes") {
                                if ($need_balance === "Yes") {
                                    $balance = 0;
                                } else {
                                    $eligible = "Yes";
                                }
                            } else {
                                if ($need_balance === "Yes") {
                                    $balanceargs = array(
                                        'applicable_from' => $leave_query->applicable_from,
                                        'doj' => $doj,
                                        'amt' => $leave_query->amt
                                    );

                                    $balance = $this->getLeaveAmountforFinancialYear($balanceargs);
                                } else {
                                    $eligible = "Yes";
                                }
                            }
                        }
                    } else {
                        if ($leave_query->prorata === "Yes") {
                            if ($need_balance === "Yes") {
                                $balance = 0;
                            } else {
                                $eligible = "Yes";
                            }
                        } else {
                            if ($need_balance === "Yes") {
                                $balanceargs = array(
                                    'applicable_from' => $leave_query->applicable_from,
                                    'doj' => $doj,
                                    'amt' => $leave_query->amt
                                );

                                $balance = $this->getLeaveAmountforFinancialYear($balanceargs);
                            } else {
                                $eligible = "Yes";
                            }
                        }
                    }
                }
                if ($need_balance === "Yes") {
                    return $balance;
                } else {
                    return $eligible;
                }

            } else if ($prorate === "Bi Monthly" && strlen(trim($doj)) > 0 && strlen(trim($current_value)) > 0) {
                echo "\n====> inside Bi Monthly ";

                // create where condition based on details
                $where = "id = " . $leave_id . " and status = 'Active' and (branch_id = 0 or FIND_IN_SET(" . $branch . ", branch_id) > 0)";
                $where = $where . " and (employment_type = 0 or FIND_IN_SET(" . $job_status . ", employment_type) > 0) ";
                $where = $where . " and (region_id = 0 or FIND_IN_SET(" . $region . ", region_id) > 0) ";

                // query to leave type table
                $leaveArgs = array(
                    'sTable' => "leave_type",
                    'countOrResult' => "row",
                    'where' => $where
                );

                $leave_query = $this->getTablelist($leaveArgs);

                if (count($leave_query) > 0) {
                    $result = substr($current_value, 0, 3);
                    echo "\n====> sub string = " . $result;
                    if ($result === "1st") {
                        echo "\n====> first_date - " . $first_date = date("Y-m-01");
                        echo "\n====> second_date - " . $second_date = date("Y-m-15");
                        if ((strtotime($doj) <= strtotime($first_date)) || (strtotime($doj) >= strtotime($first_date) && (strtotime($doj) <= strtotime($second_date)))) {
                            if ($need_balance === "Yes") {
                                return 0;
                            } else {
                                return "Yes";
                            }
                        } else {
                            if ($need_balance === "Yes") {
                                return 0;
                            } else {
                                return "No";
                            }
                        }
                    } else if ($result === "2nd") {
                        if (date('d') <= 15) {
                            echo "\n====> first_date - " . $first_date = date('Y-m-16', strtotime("last day of last month"));
                            echo "\n====> second_date - " . $second_date = date('Y-m-d', strtotime("last day of last month"));
                        } else {
                            echo "\n====> first_date - " . $first_date = date("Y-m-16");
                            echo "\n====> second_date - " . $second_date = date("Y-m-t");
                        }
                        if ((strtotime($doj) <= strtotime($first_date)) || (strtotime($doj) >= strtotime($first_date) && (strtotime($doj) <= strtotime($second_date)))) {
                            if ($need_balance === "Yes") {
                                return 0;
                            } else {
                                return "Yes";
                            }
                        } else {
                            if ($need_balance === "Yes") {
                                return 0;
                            } else {
                                return "No";
                            }
                        }
                    } else {
                        if ($need_balance === "Yes") {
                            return 0;
                        } else {
                            return "No";
                        }
                    }
                } else {
                    if ($need_balance === "Yes") {
                        return 0;
                    } else {
                        return "No";
                    }
                }
            } else {
                if ($need_balance === "Yes") {
                    return -1;
                } else {
                    return $eligible;
                }
            }
        }
    }

    public function checkIfEmployeeIsEligibleForLeaveDayWise($args)
    {

        $empid = $args['empid'];
        $leave_id = $args['leave_id'];
        $day = $args['day'];

        // collect all the employees which don't have leaves at their disposal
        $empArgs = array(
            'sTable' => "emp",
            'countOrResult' => "row",
            'where' => "status = 'Active' and id = " . $empid
        );

        $query = $this->getTablelist($empArgs);

        // check the count and then go inside if  > 0
        if (count($query) > 0) {
            // get doj
            $doj = $query->dateofjoin;

            if (strlen(trim($doj)) > 0) {
                if (strtotime($doj) > strtotime(date("Y-m-" . $day))) {
                    return "No";
                } else {
                    // get the details like branch, region, employment status, probation status
                    $branch = $query->branch;
                    $region = strlen(trim($query->region)) > 0 ? $query->region : 0;
                    $job_status = $query->job_status;
                    $probation_status = $query->probation_status;

                    // check if doj is there or not and if not then add to reminders variable
                    // and if yes then go inside for further calculations

                    // create where condition based on details
                    $where = "id = " . $leave_id . " and status = 'Active' and (branch_id = 0 or FIND_IN_SET(" . $branch . ", branch_id) > 0)";
                    $where = $where . " and (employment_type = 0 or FIND_IN_SET(" . $job_status . ", employment_type) > 0) ";
                    $where = $where . " and (region_id = 0 or FIND_IN_SET(" . $region . ", region_id) > 0) ";

                    // query to leave type table
                    $leaveArgs = array(
                        'sTable' => "leave_type",
                        'countOrResult' => "row",
                        'where' => $where
                    );

                    $leave_query = $this->getTablelist($leaveArgs);

                    if (count($leave_query) > 0) {
                        // check if employee is on probation or not and determine balance leave accordingly
                        if ($probation_status === "Probation") {
                            if ($leave_query->applicable_to_employees_on_probation === "No") {
                                return "No";
                            } else {
                                return "Yes";
                            }
                        } else {
                            return "Yes";
                        }
                    } else {
                        return "No";
                    }
                }
            } else {
                return "No";
            }
        }
    }

    public function checkHolidayOnDate($args)
    {
        $current_date = isset($args['current_date']) ? (strlen(trim($args['current_date']) === 0) ? "" : $args['current_date']) : "";
        $useYear = isset($args['useYear']) ? (strlen(trim($args['useYear'])) === 0 ? "Yes" : $args['useYear']) : "Yes";
        $countOrResult = isset($args['countOrResult']) ? $args['countOrResult'] : 'count';

        if (strlen(trim($current_date)) === 0) {
            return -1;
        } else {

            $empid = $args['empid'];

            $args = $this->getEmployeeStats(array(
                'empid' => $empid
            ));

            $args = array_merge($args, array(
                'countOrResult' => $countOrResult,
                'filterforToday' => 'Yes',
                'current_date' => $current_date
            ));

            if (isset($useYear) && strlen(trim($useYear)) > 0) {
                $args = array_merge($args, array(
                    'useYear' => $useYear,
                ));
            }

            $isHoliday = $this->getHolidayList($args);

            return $isHoliday;
        }
    }

    public function getEmployeeStats($args)
    {
        $empid = $args['empid'];

        $empDataArgs = array(
            'table' => 'emp',
            'fields' => 'department',
            'field_data' => $empid
        );

        $department = ($this->checkCountforgetDataByPassingfield($empDataArgs) > 0) ? $this->getDataByPassingfield($empDataArgs)->department : "";

        $empDataArgs = array(
            'table' => 'emp',
            'fields' => 'division',
            'field_data' => $empid
        );

        $division = ($this->checkCountforgetDataByPassingfield($empDataArgs) > 0) ? $this->getDataByPassingfield($empDataArgs)->division : "";

        $empDataArgs = array(
            'table' => 'emp',
            'fields' => 'region',
            'field_data' => $empid
        );

        $region = ($this->checkCountforgetDataByPassingfield($empDataArgs) > 0) ? $this->getDataByPassingfield($empDataArgs)->region : "";

        $empDataArgs = array(
            'table' => 'emp',
            'fields' => 'branch',
            'field_data' => $empid
        );

        $branch = ($this->checkCountforgetDataByPassingfield($empDataArgs) > 0) ? $this->getDataByPassingfield($empDataArgs)->branch : "";

        return $args = array(
            'employee' => $empid,
            'department' => $department,
            'division' => $division,
            'region' => $region,
            'branch' => $branch
        );
    }

    function getActiveEmployeeTableList($args)
    {

        $fields = isset($args['fields']) ? $args['fields'] : "";
        $where = isset($args['where']) ? $args['where'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $limit = isset($args['limit']) ? $args['limit'] : 0;
        $offset = isset($args['offset']) ? $args['offset'] : 0;
        $countOrResult = isset($args['countOrResult']) ? (strlen(trim($args['countOrResult'])) === 0 ? "result" : $args['countOrResult']) : "result";

        if (strlen(trim($fields)) > 0) {
            $this->db->select($fields, false);
        }

        if (is_array($where)) {
            $this->db->where($where);
        } else if (strlen(trim($where)) > 0) {
            $this->db->where($where);
        }

        $this->db->where("e.status = 'Active' or e.status is null");
//        $this->db->where("u.active = 1");

        if ($countOrResult === "result") {
            if (strlen(trim($sorting)) > 0) {
                $this->db->order_by($sorting);
            }
            if ($limit > 0) {
                $this->db->limit($limit, $offset);
            }
        }

        $this->db->from("emp e");
//        $this->db->join("users u", "e.id = u.empid");

        $query = $this->db->get();

//        if($this->db->error()) {
//            echo $this->db->last_query();
//            echo "\n <br/>" . $this->db->error();
//        }

        if ($countOrResult === "row") {
            return $query->row();
        } else if ($countOrResult === "count") {
            return $query->num_rows();
        } elseif ($countOrResult === "result") {
            return $query->result();
        }
    }

    /*
     * Author: Vinod Rawat
     * This function with check for the maximum balance for a leave type and
     * will maintain the balance leave for an employee accordingly
     */
    function maintain_max_balance_leave()
    {
        //STEP 1: Get the maximum balance leave from leave_type table
        $this->db->select("lt.id, el.empid, lt.leavetype, lt.maximum_balance_leave, el.balance");
        $this->db->from("leave_type lt");
        $this->db->join("emp_leaves el", "lt.id = el.type");
        $this->db->where(array('lt.status' => 'Active', 'lt.maximum_balance_leave >' => 0));

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $leave) {
                //STEP 2: Check if the employee's balance leave exceeds the maximum balance leave for a leave type
                //        Update the balance leave if it exceeds from the maximum balance leave
                if ($leave->balance > $leave->maximum_balance_leave) {

                    $data = array(
                        'mode' => "Edit",
                        'table' => "emp_leaves",
                        'where' => array(
                            'empid' => $leave->empid,
                            'type' => $leave->id
                        ),
                        'tableData' => array(
                            'balance' => $leave->maximum_balance_leave
                        )
                    );

                    $this->data_change($data);

                    $this->AddLeaveStatus(array(
                        'empid' => $leave->empid,
                        'leave_id' => $leave->id,
                        'status' => "DR",
                        'balance' => number_format((float)$leave->balance - (float)$leave->maximum_balance_leave, 2),
                        'comments' => $leave->balance - $leave->maximum_balance_leave . ' day(s) ' . $leave->leavetype . ' leave has been debited to keep maximum balance.'
                    ));

                    //STEP 3: Create an leave activity log for the transaction
                    $log_data = array(
                        'mode' => "Add",
                        'table' => "leave_activity_log",
                        'tableData' => array(
                            'empid' => $leave->empid,
                            'activity_type' => $leave->balance - $leave->maximum_balance_leave . ' day(s) ' . $leave->leavetype . ' leave',
                            'activity_date' => date('Y-m-d'),
                            'description' => 'has been debited to keep maximum balance.'
                        )
                    );

                    $this->data_change($log_data);

                }
            }

            return 1;

        } else {
            return -1;
        }

    }

    public function makeUserAdmin($args)
    {
        if ($args['mode'] === "true") {
            return $this->data_change(array(
                'mode' => "Add",
                'table' => "users_groups",
                'tableData' => array(
                    'user_id' => $args['userid'],
                    'group_id' => $args['groupid']
                )
            ));
        } else if ($args['mode'] === "false") {
            return $this->data_change(array(
                'mode' => "Del",
                'table' => "users_groups",
                'tableData' => array(
                    'user_id' => $args['userid'],
                    'group_id' => $args['groupid']
                )
            ));
        }
    }

    public function makeEmployeeVisible($args)
    {
        return $this->data_change(array(
            'mode' => "Edit",
            'table' => "emp",
            'tableData' => array(
                'is_visible' => $args['isVisible']
            ),
            'where' => array(
                'id' => $args['empid']
            )
        ));
    }

    public function makeUserAccountOwner($args)
    {
        $this->data_change(array(
            'mode' => $args['mode'],
            'table' => "users",
            'tableData' => array(
                'is_owner' => 0
            ),
            'where' => array(
                'id' => $args['current_userId']
            )
        ));

        return $this->data_change(array(
            'mode' => $args['mode'],
            'table' => "users",
            'tableData' => array(
                'is_owner' => 1
            ),
            'where' => array(
                'id' => $args['selected_userId']
            )
        ));
    }

    function getActiveAdminTableList($args)
    {

        $fields = isset($args['fields']) ? $args['fields'] : "";
        $where = isset($args['where']) ? $args['where'] : "";
        $sorting = isset($args['sorting']) ? $args['sorting'] : "";
        $limit = isset($args['limit']) ? $args['limit'] : 0;
        $offset = isset($args['offset']) ? $args['offset'] : 0;
        $countOrResult = isset($args['countOrResult']) ? (strlen(trim($args['countOrResult'])) === 0 ? "result" : $args['countOrResult']) : "result";

        if (strlen(trim($fields)) > 0) {
            $this->db->select($fields, false);
        }

        if (is_array($where)) {
            $this->db->where($where);
        } else if (strlen(trim($where)) > 0) {
            $this->db->where($where);
        }

        $this->db->where("u.active = 1");

        if ($countOrResult === "result") {
            if (strlen(trim($sorting)) > 0) {
                $this->db->order_by($sorting);
            }
            if ($limit > 0) {
                $this->db->limit($limit, $offset);
            }
        }

        $this->db->from("emp e");
        $this->db->join("users u", "e.id = u.empid");
        $this->db->join("users_groups ug", "u.id = ug.user_id");
        $this->db->where("ug.group_id = 1");

        $query = $this->db->get();

//        if($this->db->error()) {
//            echo $this->db->last_query();
//        }

        if ($countOrResult === "row") {
            return $query->row();
        } else if ($countOrResult === "count") {
            return $query->num_rows();
        } elseif ($countOrResult === "result") {
            return $query->result();
        }
    }

    function updateApprovalStatus(
        $id, $base_table, $fk_field, $update_table, $update_calculations, $syslog, $request_type, $empid, $activity_type = "", $approval_id = 0, $login_empid = "")
    {
        try {
            // pass on arguments for updating table
            $update_args = array(
                "mode" => "Edit",
                "id" => $id,
                "table" => $update_table,
            );

            // pass on arguments for selecting table
            $main_args = array(
                "sTable" => $base_table,
                "countOrResult" => "count",
                "where" => array(
                    $fk_field => $id
                )
            );

            // take the total count for seleting table for comparision
            $totalCount = $this->getTablelist($main_args);

            // this block should be executed only for expense calculation
            if ($request_type === "Expense" && $update_calculations === "Yes") {
                // check for status count with pending status
                $args = array_merge($main_args, array(
                    "where" => array(
                        "status" => 0,
                        $fk_field => $id
                    )
                ));

                // get the count and do the expense calculation
                $count = 0;
                $count = $this->getTablelist($args);

                if ((int)$count === 0) {
                    $update_args = $this->calculateExpense(array(
                        'id' => $id,
                        'empid' => $empid,
                        'update_args' => $update_args
                    ));
                }
            }

            // check for expense status here, this block is used only for partial approval for expense as now in expenses there is
            // multiple approvals for expenese types. Status Code for partial approval is 5
            if ($request_type === "Expense") {
                // check for status count with pending status
                $args = array_merge($main_args, array(
                    "where" => array(
                        "status" => 0,
                        $fk_field => $id
                    )
                ));

                $status = "";
                $count = 0;
                $count = $this->getTablelist($args);

                if ((int)$count === 0) {
                    $state = 5;
                    $status = "Partial Approve";

                    // get the rejected count
                    $args = array_merge($main_args, array(
                        "where" => array(
                            "status" => 3,
                            $fk_field => $id
                        )
                    ));

                    $count = 0;
                    $count = $this->getTablelist($args);

                    if ((int)$count === (int)$totalCount) {
                        $state = 3;
                        $status = "Reject";
                    }

                    // get the approval count
                    $args = array_merge($main_args, array(
                        "where" => array(
                            "status" => 1,
                            $fk_field => $id
                        )
                    ));

                    $count = 0;
                    $count = $this->getTablelist($args);

                    if ((int)$count === (int)$totalCount) {
                        $state = 1;
                        $status = "Approve";
                    }

                    $update_args = array_merge($update_args, array(
                        "tableData" => array(
                            "status" => $state
                        )
                    ));

                    $change = $this->data_change($update_args);
                    return $change;
                }

                $this->send_notification_to_next_approver(array(
                    'empid' => $empid,
                    'request_type' => $activity_type,
                    'action' => $status,
                    'syslog' => $syslog,
                    'table' => $update_table,
                    'id' => $id,
                    'approval_id' => $approval_id,
                    'base_table' => $base_table,
                    'login_empid' => $login_empid
                ));
            }

            // check for rejected, no need for compare with total count as even one item
            // is rejected then whole workflow is rejected, but this will not work in expense
            // as expense has multi level workflow
            if ($request_type !== "Expense") {
                $args = array_merge($main_args, array(
                    "where" => array(
                        "status" => 3,
                        $fk_field => $id
                    )
                ));

                $count = 0;
                $count = $this->getTablelist($args);

                if ((int)$count > 0) {
                    $update_args = array_merge($update_args, array(
                        "tableData" => array(
                            "status" => 3
                        )
                    ));

                    $change = $this->data_change($update_args);

                    if ($request_type !== "JobPosting" && $request_type !== "Employee Transfer") {
                        $this->notifyEmployeesForArrovalsRejections(array(
                            'empid' => $empid,
                            'request_type' => $activity_type,
                            'action' => "Reject",
                            'syslog' => $syslog,
                            'table' => $update_table,
                            'id' => $id,
                            'approval_id' => $approval_id,
                            'base_table' => $base_table,
                            'login_empid' => $login_empid
                        ));
                    }
                    return $change;
                }
            }

            // check for approved, compare with total count as all are approved then status will be 1
            $args = array();
            $args = array_merge($main_args, array(
                "where" => array(
                    "status" => 1,
                    $fk_field => $id
                )
            ));

            $count = 0;
            $count = $this->getTablelist($args);

            if ((int)$count === (int)$totalCount) {
                if ($update_calculations === "Yes") {
                    if ($request_type === "Leave") {
                        $update_args = $this->calculateLeaves(array(
                            'update_table' => $update_table,
                            'id' => $id,
                            'update_args' => $update_args
                        ));
                    } else if ($request_type === "Resignation") {
                        $update_args = $this->calculateResignation(array(
                            'update_table' => $update_table,
                            'id' => $id,
                            'update_args' => $update_args
                        ));
                    }

                } else {
                    // pass on only status to array as if update parameter set to No
                    $update_args = array_merge($update_args, array(
                        "tableData" => array(
                            "status" => 1
                        )
                    ));
                }

                $change = $this->data_change($update_args);

                //If request type is Asset then no need to add activity log
                if ($request_type === "Asset" || $request_type === "Project kpi") {
                    $syslog = "No";
                }

                if ($syslog === "Yes") {
                    if ($request_type !== "Ticket") {
                        $logargs = array(
                            'mode' => 'Add',
                            'table' => strtolower($request_type) . '_activity_log',
                            'tableData' => array(
                                'empid' => $empid,
                                'activity_date' => date('Y-m-d'),
                                'activity_type' => $request_type . " request",
                                'description' => "has been approved by system."
                            )
                        );

                        $this->data_change($logargs);
                    }
                }

                if ($request_type !== "Expense" && $request_type !== "JobPosting" && $request_type !== "Employee Transfer") {
                    $this->notifyEmployeesForArrovalsRejections(array(
                        'empid' => $empid,
                        'request_type' => $activity_type,
                        'action' => "Approve",
                        'syslog' => $syslog,
                        'table' => $update_table,
                        'id' => $id,
                        'approval_id' => $approval_id,
                        'base_table' => $base_table,
                        'login_empid' => $login_empid
                    ));
                }

                return $change;
            } else {
                $data = array(
                    'sTable' => $base_table,
                    'fields' => "count(" . $fk_field . ") as count",
                    'where' => "status = 1 and " . $fk_field . " = " . $id . " and number_in_sequence = (select max(number_in_sequence) from " .
                        $base_table . " where " . $fk_field . " = " . $id . "  and emp_approve_state = 'Done')",
                    'countOrResult' => "row"
                );

                if ($request_type !== "Expense" && $request_type !== "JobPosting" && $request_type !== "Employee Transfer") {
                    if ((int)$this->getTablelist($data)->count > 0) {
                        $this->notifyEmployeesForArrovalsRejections(array(
                            'empid' => $empid,
                            'request_type' => $activity_type,
                            'action' => "Approve",
                            'level' => "Next",
                            'syslog' => $syslog,
                            'table' => $update_table,
                            'id' => $id,
                            'approval_id' => $approval_id,
                            'base_table' => $base_table,
                            'login_empid' => $login_empid
                        ));
                    }
                }

//                return;
            }

            // check for pending, compare with total count as all are pending then status will be 0
            $args = array();
            $args = array_merge($main_args, array(
                "where" => array(
                    "status" => 0,
                    $fk_field => $id
                )
            ));

            $count = 0;
            $count = $this->getTablelist($args);

            if ((int)$count === (int)$totalCount) {
                $update_args = array_merge($update_args, array(
                    "tableData" => array(
                        "status" => 0
                    )
                ));

                return $this->data_change($update_args);
            }

            // if anything don't work then make it in progress i.e. 2
            $update_args = array_merge($update_args, array(
                "tableData" => array(
                    "status" => 2
                )
            ));

            return $this->data_change($update_args);

        } catch (Exception $e) {
            return false;
        }
    }

    function calculateLeaves($args)
    {
        $this->load->library("commonlib");
        $whentodeduct = $this->commonlib->get_config_variable('LEAVE', 'when_to_deduct_leave');
        $negative_balance = $this->commonlib->get_config_variable('LEAVE', 'allow_negative_balance_upto');
        $update_table = $args["update_table"];
        $id = $args["id"];
        $update_args = $args["update_args"];

        // get the leave applied details
        $AppliedArgs = array(
            'table' => $update_table,
            'field_data' => $id,
            'fields' => 'empid, leavetypeid, totaldays, actualdays, transtype, paiddays'
        );

        $employee_applied_data = $this->getDataByPassingfield($AppliedArgs);

        $is_lwp = $this->getTablelist(array(
            'sTable' => 'leaves_applied la',
            'fields' => 'lt.is_lwp',
            'joinlist' => array(
                array(
                    'table' => "leave_type lt",
                    'condition' => "lt.id = la.leavetypeid",
                    'join' => ""
                )
            ),
            'where' => "lt.id = " . $employee_applied_data->leavetypeid,
            'countOrResult' => 'row'
        ))->is_lwp;

        // get the employee leave balance details
        $empArgs = array(
            'sTable' => 'emp_leaves',
            'where' => array(
                'empid' => $employee_applied_data->empid,
                'type' => $employee_applied_data->leavetypeid
            ),
            'fields' => 'id, balance, total_balance',
            'countOrResult' => 'row'
        );

        $employees_balance = $this->getTablelist($empArgs);

        // format to float for accurate balances
        $balance_leaves = number_format(floatval($employees_balance->balance), 2);
        $total_leaves = number_format(floatval($employee_applied_data->actualdays), 2);

        $cancel_args = array(
            'sTable' => $update_table,
            'fields' => 'id',
            'where' => "cancelled_parent_id = " . $id,
            'countOrResult' => "count"
        );

        $cancelled_parent_id_count = $this->getTablelist($cancel_args);

        if ((int)$cancelled_parent_id_count > 0) {
            $paiddays = 0;
            $totalBalance = 0;

            $paiddays = number_format(floatval($employee_applied_data->paiddays), 2);
            $totalBalance = number_format(floatval($employees_balance->total_balance), 2);
            if ($employee_applied_data->transtype === "DR") {
                $transtype = "CR";
                $paiddays = $paiddays + $balance_leaves;
                $totalBalance = $totalBalance;
            } else if ($employee_applied_data->transtype === "CR") {
                $transtype = "DR";
                $paiddays = (float)$balance_leaves - (float)$employee_applied_data->totaldays;
                $totalBalance = (float)$totalBalance - (float)$employee_applied_data->totaldays;
            }

            if ($is_lwp === "No") {
                $emp_leaves = array(
                    'mode' => "Edit",
                    'id' => $employees_balance->id,
                    'table' => 'emp_leaves',
                    'tableData' => array(
                        'balance' => $paiddays,
                        'total_balance' => $totalBalance
                    )
                );

                $this->data_change($emp_leaves);

                $this->AddLeaveStatus(array(
                    'empid' => $employee_applied_data->empid,
                    'leave_id' => $employee_applied_data->leavetypeid,
                    'status' => $transtype,
                    'balance' => number_format((float)$total_leaves, 2)
                ));
            }

            // pass on leave status details to array
            $update_args = array_merge($update_args, array(
                "tableData" => array(
                    "status" => 1,
                )
            ));
        } else {
            $paid_leave = 0;
            $unpaid_leave = 0;
            $totalBalance = 0;

            // check for transaction type, based upon get the paid and unpaid leaves
            if ($employee_applied_data->transtype === "DR") {
                if ((floatval($balance_leaves) + floatval($negative_balance)) < floatval($total_leaves)) {
                    $paid_leave = floatval($balance_leaves) + floatval($negative_balance);
                    if ((float)$balance_leaves >= 0) {
                        $unpaid_leave = $total_leaves - (floatval($balance_leaves) + floatval($negative_balance));
                    } else {
                        $unpaid_leave = floatval($total_leaves) - floatval($paid_leave);
                    }
                } else if ((floatval($balance_leaves) + floatval($negative_balance)) >= $total_leaves) {
                    $paid_leave = $total_leaves;
                    $unpaid_leave = 0;
                }
                $totalBalance = number_format(floatval($employees_balance->total_balance), 2);

            } else if ($employee_applied_data->transtype === "CR") {
                $paid_leave = $total_leaves + $balance_leaves;
                $totalBalance = number_format(floatval($employees_balance->total_balance), 2) + $total_leaves;
                $unpaid_leave = 0;
            }

            if ($is_lwp === "Yes") {
                $paid_leave = 0;
                $unpaid_leave = $total_leaves;
            }

            // deduct paid leaves from employee's balance leaves
            if ($paid_leave > 0) {

                if ($employee_applied_data->transtype === "DR") {
                    $changed_leave_amount = $balance_leaves - $paid_leave;
                } else {
                    $changed_leave_amount = $paid_leave;
                }

                if (($whentodeduct === "While Approving" || $employee_applied_data->transtype === "CR") && $is_lwp === "No") {
                    $emp_leaves = array(
                        'mode' => "Edit",
                        'id' => $employees_balance->id,
                        'table' => 'emp_leaves',
                        'tableData' => array(
                            'balance' => $changed_leave_amount,
                            'total_balance' => $totalBalance
                        )
                    );

                    $this->data_change($emp_leaves);
                    //echo $this->db->last_query();

                    $this->AddLeaveStatus(array(
                        'empid' => $employee_applied_data->empid,
                        'leave_id' => $employee_applied_data->leavetypeid,
                        'status' => $employee_applied_data->transtype,
                        'balance' => $total_leaves
                    ));
                }
            }

            // pass on leave status, paid and unpaid details to array
            if ($whentodeduct === "While Applying") {
                $update_args = array_merge($update_args, array(
                    "tableData" => array(
                        "status" => 1
                    )
                ));
            } else {
                $update_args = array_merge($update_args, array(
                    "tableData" => array(
                        "status" => 1,
                        "paiddays" => ($employee_applied_data->transtype === "DR") ? $paid_leave : 0,
                        "unpaiddays" => ($employee_applied_data->transtype === "DR") ? $unpaid_leave : 0
                    )
                ));
            }

        }

        return $update_args;
    }

    function calculateResignation($args)
    {
        $update_table = $args["update_table"];
        $id = $args["id"];
        $update_args = $args["update_args"];

        // get the resignation applied details
        $AppliedArgs = array(
            'table' => $update_table,
            'field_data' => $id,
            'fields' => 'empid, resign_date, last_date, approved_last_date, reason'
        );

        $employee_applied_data = $this->getDataByPassingfield($AppliedArgs);

        $last_date = strlen(trim($employee_applied_data->approved_last_date)) > 0 ? $employee_applied_data->approved_last_date : $employee_applied_data->last_date;

        $this->data_change(array(
            'mode' => "Edit",
            'table' => "emp",
            'id' => $employee_applied_data->empid,
            'tableData' => array(
                'reason' => $employee_applied_data->reason,
                'resigneddate' => $employee_applied_data->resign_date,
                'lastdayofemployment' => $last_date,
                'status' => "Resigned"
            )
        ));

        $update_args = array_merge($update_args, array(
            "tableData" => array(
                "status" => 1,
                "approved_last_date" => $last_date
            )
        ));

        return $update_args;
    }

    public function calculateExpense($args)
    {
        $empid = $args["empid"];
        $expense_request_id = $args["id"];
        $update_args = $args["update_args"];

        $expense_amount = 0;

        //STEP 1: Get the expense details from expense_amount_detail table
        $expense_amt_detail = $this->getTablelist(array(
            'sTable' => "expense_amount_detail",
            'where' => "expense_applied_id = " . $expense_request_id
        ));

        if (count($expense_amt_detail) > 0) {
            foreach ($expense_amt_detail as $row) {

                $total_count = $this->getTablelist(array(
                    'sTable' => "expense_approval",
                    'fields' => "id",
                    'where' => "expense_applied_id = " . $expense_request_id . " and expense_type_id = " . $row->expense_type_id . " and uid = " . $row->uid,
                    'countOrResult' => "count"
                ));

                $approved_count = $this->getTablelist(array(
                    'sTable' => "expense_approval",
                    'fields' => "id",
                    'where' => "expense_applied_id = " . $expense_request_id . " and expense_type_id = " . $row->expense_type_id . " and status = 1" . " and uid = " . $row->uid,
                    'countOrResult' => "count"
                ));

                if ((int)$total_count === (int)$approved_count) {
                    //STEP 2: Get the expense limt and balance for an expense type
                    $expenseType_advance = $this->getTablelist(array(
                        'sTable' => "expense_type et",
                        'joinlist' => array(
                            array(
                                "table" => "expense_type_detail etd",
                                "condition" => "et.id = etd.expense_type_id",
                                "type" => "left"
                            )
                        ),
                        'countOrResult' => "row",
                        'where' => "etd.expense_type_id = " . $row->expense_type_id
                    ));

                    if (count($expenseType_advance) > 0) {
                        $empdetails = $this->getTablelist(array(
                            'fields' => "e.department, e.grade",
                            'sTable' => "emp e",
                            'where' => 'id = ' . $empid,
                            'countOrResult' => 'row'
                        ));

                        if (count($empdetails) > 0) {
                            $department_id = $empdetails->department;
                            $grade_id = $empdetails->grade;
                        } else {
                            $department_id = 0;
                            $grade_id = 0;
                        }

                        switch ($expenseType_advance->apply_to) {
                            case "Employee":
                                $criteria_id = $empid;
                                break;
                            case "Department":
                                $criteria_id = $department_id;
                                break;
                            case "Grade":
                                $criteria_id = $grade_id;
                                break;
                        }

                        $expense_budget = $this->getTablelist(array(
                            'sTable' => "expense_budget eb",
                            'countOrResult' => "row",
                            'where' => "eb.status = 'Active' and eb.expense_type_detail_id = " . $expenseType_advance->id .
                                " and criteria = '" . $expenseType_advance->apply_to . "'" .
                                " and criteria_id = " . $criteria_id
                        ));

                        //STEP 3: Finally deduct from the balance amount
                        if (count($expense_budget) > 0) {
                            $args = array(
                                'mode' => "Edit",
                                'table' => "expense_budget",
                                'id' => $expense_budget->id,
                                'tableData' => array(
                                    'balance' => (float)$expense_budget->balance - (float)$row->approved_amount
                                )
                            );

                            $this->data_change($args);
                        }
                    }

                    $expense_amount = $expense_amount + (float)$row->approved_amount;
                }
            }
        }

        if (count($expense_amt_detail) > 0) {
            $this->data_change(array(
                'mode' => "Edit",
                'table' => "expense",
                'id' => $expense_request_id,
                'tableData' => array(
                    'approved_amt' => $expense_amount
                )
            ));
        }

        $update_args = array_merge($update_args, array(
            "tableData" => array(
                "status" => 1,
            )
        ));

        return $update_args;
    }

    function notifyEmployeesForArrovalsRejections($args)
    {

        $empid = $args['empid'];
        $request_type = $args['request_type'];
        $action = $args['action'] === "Reject" ? "Rejected" : ($args['action'] === "Approve" ? "Approved" : "");

        $level = isset($args['level']) ? (strlen(trim($args['level'])) === 0 ? "" : "Your request is waiting for next approval(s).") : "";
        $system = isset($args['syslog']) ? (strlen(trim($args['syslog'])) === 0 ? "No" : $args['syslog']) : "No";

        $table = $args['table'];
        $id = $args['id'];

        $base_table = $args['base_table'];

        $emp = $this->getTablelist(array(
            'fields' => "id, concat_ws(' ', firstname, lastname) as ename, office_email",
            'sTable' => "emp",
            'where' => "id = " . $empid,
            'countOrResult' => "row"
        ));

        $email = $emp->office_email;
        $employee_name = $emp->ename;
        $approval_id = $args['approval_id'];

        $fields = "*";

        //First get the currently login employee id
        if ($this->ion_auth->logged_in()) {
            $login_empid = $this->ion_auth->user()->row()->empid;
        } else {
            if ($args['login_empid'] !== "") {
                $login_empid = $args['login_empid'];
            } else {
                $login_empid = $empid;
            }
        }

        $data = array();
        if ($request_type === "Leave Request") {
            $fields = "concat(format(totaldays, 2), ' day(s) from ', date_format(startdate, '%M %e, %Y'), ' to ' , date_format(enddate, '%M %e, %Y')) as data";
            $leave_applied_status = $this->commonmodel->getTablelist(array(
                'sTable' => "leaves_applied",
                'fields' => "applied_status, status",
                'where' => "id = " . $id,
                'countOrResult' => "row"
            ));
            if ($leave_applied_status->applied_status === 'Applied') {
                $data = array(
                    "event_name" => "LEAVE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    'account_name' => getDomain(),
                    "data" => array(
                        "leaves_applied_id" => $id,
                        "leaves_approval_id" => $approval_id,
                        "empid" => $empid,
                        "to_email" => $email,
                        "event_name" => "LEAVE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                        "status" => $action,
                        "account_name" => getDomain()
                    )
                );
            } else if ($leave_applied_status->applied_status === 'Cancelled') {
                $data = array(
                    "event_name" => "LEAVE_CANCEL_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    'account_name' => getDomain(),
                    "data" => array(
                        "leaves_applied_id" => $id,
                        "leaves_approval_id" => $approval_id,
                        "empid" => $empid,
                        "to_email" => $email,
                        "event_name" => "LEAVE_CANCEL_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                        "status" => $action,
                        "account_name" => getDomain()
                    )
                );
            }

        } else if ($request_type === "Travel Request") {
            $fields = "concat('travelling to ', location, ' for ', format(noofdays, 2), ' day(s) from ', date_format(from_date, '%M %e, %Y'), ' to ' , date_format(to_date, '%M %e, %Y')) as data";
            $data = array(
                "event_name" => "TRAVEL_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "travel_request_id" => $id,
                    "travel_approval_id" => $approval_id,
                    "empid" => $empid,
                    "to_email" => $email,
                    "event_name" => "TRAVEL_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );

        } else if ($request_type === "Attendance Request") {
            $fields = "concat('applied for rationalization ', date_format(substr(check_in,1,10), '%e-%b-%Y'), ' Check-In:', substr(check_in,11,6), ' Check-Out:', substr(check_out,11,6)) as data";
            $data = array(
                "event_name" => "ATTENDANCE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "attendance_request_id" => $id,
                    "attendance_approval_id" => $approval_id,
                    "empid" => $empid,
                    "to_email" => $email,
                    "event_name" => "ATTENDANCE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );

        } else if ($request_type === "Resignation Request") {
            $this->load->library("commonlib");
            $send_final_email = $this->commonlib->get_config_variable('RESIGNATION', "Send final approval email to employee for resignation");

            if ($send_final_email === "Yes") {
                $resignation_details_count = $this->getTablelist(array(
                    'fields' => "rr.id",
                    'sTable' => "resignation_request rr",
                    'joinlist' => array(
                        array(
                            'table' => "resignation_approval ra",
                            'condition' => "ra.resignation_request_id = rr.id",
                            'type' => ""
                        )
                    ),
                    'where' => "rr.id = $id and ra.emp_approve_state in ('Active', 'Pending')",
                    'countOrResult' => 'count'
                ));

                if (intval($resignation_details_count) === 0) {
                    $fields = "concat('Resigned on ', date_format(resign_date, '%e-%b-%Y'), ' Notice Period:', approved_notice_period, ' Last Working Date:', IF(approved_last_date is null, '-', date_format(approved_last_date, '%e-%b-%Y'))) as data";
                    $data = array(
                        "event_name" => "RESIGNATION_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                        'account_name' => getDomain(),
                        "data" => array(
                            "resignation_request_id" => $id,
                            "resignation_approval_id" => $approval_id,
                            "empid" => $empid,
                            "to_email" => $email,
                            "event_name" => "RESIGNATION_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                            "status" => $action,
                            "account_name" => getDomain()
                        )
                    );
                }
            }

        } else if ($request_type === "Ticket Request") {
            $fields = "concat('Subject - ', subject) as data";
            $data = array(
                "event_name" => "TICKET_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "ticket_id" => $id,
                    "ticket_approval_id" => $approval_id,
                    "event_name" => "TICKET_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );

        } else if ($request_type === "Asset Request") {
            $this->load->model('assetmodel');
            $asset_applied_detail = $this->assetmodel->get_asset_applied_details($id);
            $details = ucfirst($asset_applied_detail->asset_type_name) . " " . ucfirst($asset_applied_detail->asset_name);
            $data = array(
                "event_name" => "ASSET_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "asset_applied_id" => $id,
                    "asset_approval_id" => $approval_id,
                    "empid" => $empid,
                    "to_email" => $email,
                    "event_name" => "ASSET_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );
        } else if ($request_type === "Loan Request") {

            $fields = "concat('Loan Amount: Rs.', approved_loan_amount, ' Emi Amount: ', monthly_installment,' No of Installments: ', approved_no_of_installments ,' Rate on interest:', approved_rate_on_interest) as data";
            $data = array(
                "event_name" => "LOAN_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "loan_request_id" => $id,
                    "loan_approval_id" => $approval_id,
                    "empid" => $empid,
                    "to_email" => $email,
                    "event_name" => "LOAN_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );
        } else if ($request_type === "Prepayment Request") {

            $fields = "concat('Prepayment Amount : Rs.', prepayment_amount, ' Process Type: ', process_type,' Pay Date : ', date_format(pay_date, '%e-%b-%Y') ,' Description : ', description) as data";
            $data = array(
                "event_name" => "PREPAYMENT_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                'account_name' => getDomain(),
                "data" => array(
                    "loan_prepayment_request_id" => $id,
                    "loan_prepayment_approval_id" => $approval_id,
                    "empid" => $empid,
                    "to_email" => $email,
                    "event_name" => "PREPAYMENT_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    "status" => $action,
                    "account_name" => getDomain()
                )
            );
        }
        $email_args = array(
            'message' => $data
        );
        $this->load->library("awsnotification");
        $this->awsnotification->sns_easyhr_event_notification($email_args);

        $details = $this->getTablelist(array(
            'fields' => $fields,
            'sTable' => $table,
            'where' => "id = " . $id,
            'countOrResult' => 'row'
        ))->data;

        $emp = $this->getTablelist(array(
            'fields' => "id, concat_ws(' ', firstname, lastname) as ename",
            'sTable' => "emp",
            'where' => "id = " . $login_empid,
            'countOrResult' => "row"
        ));
        $approver_name = $emp->ename;
        $instead_approver_name = "";

        if ((int)$approval_id > 0) {
            $approved_empid = $this->getTablelist(array(
                'sTable' => $base_table,
                'fields' => "empid",
                'where' => "id = " . $approval_id,
                'countOrResult' => "row"
            ))->empid;

            if ((int)$login_empid !== (int)$approved_empid) {
                $emp = $this->getTablelist(array(
                    'fields' => "concat_ws(' ', firstname, lastname) as ename",
                    'sTable' => "emp",
                    'where' => "id = " . $approved_empid,
                    'countOrResult' => "row"
                ));

                $instead_approver_name = $emp->ename;
            }
        }

        if (strlen(trim($instead_approver_name)) === 0) {
            $words_wohtml = "Your " . strtolower($request_type) . " for " . $details . " has been " . $action . " by " . $approver_name;
        } else {
            $words_wohtml = "Your " . strtolower($request_type) . " for " . $details . " has been " . $action . " by " . $approver_name;
        }

        $this->load->library("commonlib");
        $this->commonlib->sendPushNotification($empid, $request_type . " " . $action, $id, $words_wohtml);

        //Send email notification to next approver if any
        $this->send_notification_to_next_approver($args);
    }

    function notifyEmployeesForArrovalsRejections_Expense($args)
    {
        $empid = $args['empid'];
        $action = $args['action'] === "Reject" ? "Rejected" : ($args['action'] === "Approve" ? "Approved" : "");
        $id = $args['id'];
        $other_ids = $args['details'];

        $emp = $this->getTablelist(array(
            'fields' => "id, concat_ws(' ', firstname, lastname) as ename, office_email",
            'sTable' => "emp",
            'where' => "id = " . $empid,
            'countOrResult' => "row"
        ));

        $email = $emp->office_email;
        foreach ($other_ids as $idx => $ids) {
            if (isset($email) && strlen(trim($email)) > 0) {
                $data = array(
                    "event_name" => "EXPENSE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                    'account_name' => getDomain(),
                    "data" => array(
                        "expense_id" => $id,
                        "expense_approval_id" => $ids["approval_id"],
                        "empid" => $empid,
                        "to_email" => $email,
                        "event_name" => "EXPENSE_APPROVAL_NOTIFICATION_TO_EMPLOYEE",
                        "status" => $action,
                        "account_name" => getDomain()
                    )
                );
                $email_args = array(
                    'message' => $data
                );
                $this->load->library("awsnotification");
                $this->awsnotification->sns_easyhr_event_notification($email_args);
            }
        }

    }

    function get_approval_details($args)
    {
        $type = isset($args['type']) ? strlen(trim($args['type'])) === 0 ? "" : $args['type'] : "";
        $empid = isset($args['empid']) ? strlen(trim($args['empid'])) === 0 ? 0 : $args['empid'] : 0;
        $id = isset($args['id']) ? strlen(trim($args['id'])) === 0 ? 0 : $args['id'] : 0;

        $limit = isset($args['limit']) ? strlen(trim($args['limit'])) === 0 ? 0 : $args['limit'] : 0;

        if (strlen(trim($type)) === 0 && (int)$empid === 0 && (int)$id === 0) {
            return 0;
        } else if ($type === "Leave") {
            $select = "em.id empid, la.id apply_id, app.id approval_id, lt.leavetype, la.file_link, la.file_name, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " concat_ws(' ', format(la.totaldays, 2), 'Day(s)', lt.leavetype, 'Leave', IF (la.transtype = 'CR', '(Credit)', '') ) details, ";
            $select .= " date_format(la.startdate, '%e-%b-%Y') as startdate, date_format(la.enddate, '%e-%b-%Y') as enddate, em.picture, la.description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('leaves_applied as la', 'em.id = la.empid');
            $this->db->join('leave_type as lt', 'lt.id = la.leavetypeid');
            $this->db->join('leaves_approval as app', 'la.id = app.leaves_applied_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'la.id' => $id
            );
        } else if ($type === "Travel") {
            $select = "em.id empid, tr.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " concat_ws(' ', format(tr.noofdays, 2), 'Day(s)', 'to', tr.location) details, ";
            $select .= " date_format(tr.from_date, '%e-%b-%Y') as startdate, date_format(tr.to_date, '%e-%b-%Y') as enddate, em.picture, tr.purpose as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('travel_request as tr', 'em.id = tr.empid');
            $this->db->join('travel_approval as app', 'tr.id = app.travel_request_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'tr.id' => $id
            );
        } else if ($type === "Expense") {
            $select = "em.id empid, ex.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ex.file_link, ex.file_name, ";
            $select .= " concat_ws(' ', 'Rs. ', amount) details, ";
            $select .= " date_format(ex.from_date, '%e-%b-%Y') as startdate, date_format(ex.to_date, '%e-%b-%Y') as enddate, em.picture, ex.purpose as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('expense as ex', 'em.id = ex.empid');
            $this->db->join('expense_type as et', 'et.id = ex.expense_type_id');
            $this->db->join('expense_approval as app', 'ex.id = app.expense_applied_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'ex.id' => $id
            );
        } else if ($type === "Attendance") {
            $select = "em.id empid, ar.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " concat('applied for rationalize ', date_format(substr(ar.check_in,1,10), '%e-%b-%Y'), ' Check-In:', substr(ar.check_in,11,6), ' Check-Out:', substr(ar.check_out,11,6)) details, ";
            $select .= " date_format(ar.check_in, '%e-%b-%Y') as startdate, date_format(ar.check_out, '%e-%b-%Y') as enddate, em.picture, ar.reason as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('attendance_request as ar', 'em.id = ar.empid');
            $this->db->join('attendance_approval as app', 'ar.id = app.attendance_request_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'ar.id' => $id
            );
        } else if ($type === "Resignation") {
            $select = "em.id empid, rr.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " concat('Resigned on ', date_format(rr.resign_date, '%e-%b-%Y'), ' Notice Period:', rr.approved_notice_period, ' Last Working Date:', date_format(rr.last_date, '%e-%b-%Y')) details, ";
            $select .= " date_format(rr.resign_date, '%e-%b-%Y') as startdate, date_format(rr.last_date, '%e-%b-%Y') as enddate, em.picture, rr.reason as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('resignation_request as rr', 'em.id = rr.empid');
            $this->db->join('resignation_approval as app', 'rr.id = app.resignation_request_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'rr.id' => $id
            );
        } else if ($type === "Goal Approval") {
            $select = "em.id empid, egs.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " '' details, ";
            $select .= " '' startdate, '' enddate, em.picture, '' description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('emp_goal_sheet as egs', 'em.id = egs.empid');
            $this->db->join('emp_goal_sheet_approval as app', 'egs.id = app.emp_goal_sheet_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'egs.id' => $id
            );
        } else if ($type === "Goal Review") {
            $select = "em.id empid, egs.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " '' details, ";
            $select .= " '' startdate, '' enddate, em.picture, '' description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('emp_goal_sheet as egs', 'em.id = egs.empid');
            $this->db->join('emp_goal_sheet_review_approval as app', 'egs.id = app.emp_goal_sheet_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'egs.id' => $id
            );
        } else if ($type === "JobPosting") {
            $select = "em.id empid, rnj.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " '' details, ";
            $select .= " '' startdate, '' enddate, em.picture, '' description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('recruit_new_jobs as rnj', 'em.id = rnj.empid');
            $this->db->join('recruit_new_jobs_approval as app', 'rnj.id = app.recruit_new_jobs_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'rnj.id' => $id
            );
        } else if ($type === "Ticket") {

            $comment = $this->getTablelist(array(
                'sTable' => "ticket_comment",
                'fields' => "comments",
                'where' => "ticket_id = $id and id = (select min(id) from ticket_comment where ticket_id = $id)",
                'countOrResult' => "row"
            ));

            if (count($comment) > 0) {
                $comment = strlen(trim($comment->comments)) > 0 ? $comment->comments : "";
            } else {
                $comment = "";
            }

            $field = "concat('<b>#', t.id, ' - ', t.subject, '</b><br/>'
                     'Category: <b>', tc.category_name, '</b><br/>',
                     'Subcategory: <b>', ts.subcategory_name, ' - [SLA ', ts.sla, ' ', ts.sla_unit, ']', '</b><br/>',
                     'Type: <b>', tt.ticket_type, '    ', 'Priority: <b>', tp.priority_name, '</b><br/>',
                     'Assiged To: <b>', concat_ws(' ', em1.firstname, em1.lastname), '</b><br/>',
                     '$comment')";

            $select = "em.id empid, t.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " $field as details, em.picture, '' description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('ticket as t', 'em.id = t.empid');
            $this->db->join('ticket_approval as app', 't.id = app.ticket_id');
            $this->db->join('ticket_category tc', 't.category = tc.id');
            $this->db->join('ticket_subcategory ts', 't.subcategory = ts.id');
            $this->db->join('ticket_type tt', 't.tickettype = tt.id');
            $this->db->join('ticket_priority tp', 't.priority = tp.id');
            $this->db->join("emp as em1", "em1.id = t.assigned_to");

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                't.id' => $id
            );
        } else if ($type === "Employee Transfer") {
            $select = "em2.id empid, et.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em1.firstname, em1.lastname) as ename, concat_ws(' ', em2.firstname, em2.lastname) as ename1, ";
            $select .= $this->get_employee_transfer_approval_details($id) . " as details, ";
            $select .= " '' as startdate, '' as enddate, em2.picture, '' as description";

            $this->db->select($select, false);
            $this->db->from("employee_transfer as et");
            $this->db->join('emp as em1', 'em1.id = et.who');
            $this->db->join('emp as em2', 'em2.id = et.empid');
            $this->db->join('employee_transfer_approval as app', 'et.id = app.employee_transfer_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'et.id' => $id
            );
        } else if ($type === "Asset") {
            $select = "em.id empid, aa.id apply_id, aap.id approval_id, a.asset_name, at.asset_type_name, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= " concat_ws(' ', at.asset_type_name, a.asset_name) details, ";
            $select .= " date_format(aa.request_date, '%e-%b-%Y') as startdate, date_format(aa.return_date, '%e-%b-%Y') as enddate, em.picture";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('asset_applied as aa', 'em.id = aa.empid');
            $this->db->join('assets as a', 'a.id = aa.asset_id');
            $this->db->join('asset_type as at', 'at.id = aa.asset_type_id');
            $this->db->join('asset_approval as aap', 'aa.id = aap.asset_applied_id');

            $data_args = array(
                'aap.status' => 0,
                'emp_approve_state' => 'Active',
                'aap.empid' => $empid,
                'aa.id' => $id
            );
        } else if ($type === "Loan") {
            $select = "em.id empid, rr.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= "  concat(' Loan Type :', rr.loan_type, ' Loan Amount : Rs.',rr.loan_amount, ' No of installments : ',rr.no_of_installments,' Monthly Installment : Rs.',rr.monthly_installment , ' Rate on interest : ',rr.rate_on_interest,'%') details, ";
            $select .= " rr.description as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('pay_loan_request as rr', 'em.id = rr.emp_id');
            $this->db->join('pay_loan_approval as app', 'rr.id = app.loan_request_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'rr.id' => $id
            );
        } else if ($type === "Prepayment") {
            $select = "em.id empid, rr.id apply_id, app.id approval_id, ";
            $select .= "concat_ws(' ', em.firstname, em.lastname) as ename, ";
            $select .= "  concat(' Prepayment Amount : Rs.',rr.prepayment_amount, ' Process Type : ',rr.process_type,' Pay Date : ',date_format(rr.pay_date, '%e-%b-%Y')) details, ";
            $select .= " rr.description as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('pay_loan_prepayment_request as rr', 'em.id = rr.empid');
            $this->db->join('pay_loan_prepayment_approval as app', 'rr.id = app.loan_prepayment_request_id');
            //  $this->db->join('pay_loan_request as lr', 'lr.id = rr.loan_request_id');

            $data_args = array(
                'app.status' => 0,
                'emp_approve_state' => 'Active',
                'app.empid' => $empid,
                'rr.id' => $id
            );
        }

        $this->db->where($data_args);

        if ((int)$limit > 0) {
            $this->db->limit($limit, 0);
        }

        $query = $this->db->get();
        return $query->row();
    }

    function get_employee_transfer_approval_details($id)
    {
        $select = "et.which_field, et.what_value,";
        $select .= " concat_ws(' ', em1.firstname, em1.lastname) as ename, concat_ws(' ', em2.firstname, em2.lastname) as ename2,";
        $select .= " '' details";

        $this->db->select($select, false);
        $this->db->from("employee_transfer as et");
        $this->db->join('emp as em1', 'em1.id = et.who');
        $this->db->join('emp as em2', 'em2.id = et.empid');

        $this->db->where("et.id", $id);

        $query = $this->db->get()->row();

        $data = "'" . $query->ename . "<small> has requested for transfer of <strong>";
        $data .= $query->ename2 . "</strong> to " . ucfirst($query->which_field) . " - " . $this->getTransferDetails($query->which_field, $query->what_value);
        $data .= "</small>'";

        return $data;
    }

    function getTransferDetails($to, $to_value)
    {
        if ($to === "manager") {
            $table = "emp";
            $field = "concat_ws(' ', firstname, lastname) as ename";
            $field1 = "ename";
        } else if ($to === "department") {
            $table = "dept";
            $field = "deptname";
            $field1 = "deptname";
        } else {
            $table = $to;
            $field = $to;
            $field1 = $to;
        }

        return $this->getTablelist(array(
            'sTable' => $table,
            'fields' => $field,
            'where' => "id = " . $to_value,
            'countOrResult' => "row"
        ))->$field1;
    }

    public function getDataChangeNotificationData()
    {

        $data = array();
        $this->db->select("distinct e.id, CONCAT_WS(' ', e.firstname, e.lastname) name", false);
        $this->db->from("emp e");
        $this->db->join("emp_data_approval eda", "e.id = eda.empid");
        $this->db->where(array(
            'eda.status' => 0,
            'eda.mail_status' => 0,
        ));
        $this->db->order_by("e.id, eda.id");

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $employees = $query->result();

            foreach ($employees as $emp) {
                $this->db->select("CONCAT_WS(' ', REPLACE(REPLACE(emp_table, '_',  ' '), 'emp', 'employee') , ':', emp_field) data_change, old_label, new_label, createdon, batch_id", false);
                $this->db->from("emp_data_approval eda");
                $this->db->where(array(
                    'eda.status' => 0,
                    'eda.mail_status' => 0,
                    'eda.empid' => $emp->id
                ));
                $this->db->order_by("eda.id");

                $emp_query = $this->db->get();

                $data = array_merge($data, array(array(
                    'ename' => $emp->name,
                    'data' => $emp_query->result()
                )));
            }
        }

        return $data;
    }

    public function getHashTableKey($key)
    {
        return $this->getDataByPassingfield(array(
            'table' => "hash_table",
            'override_field' => "key",
            'field_data' => $key,
            'fields' => "*"
        ));
    }

    public function checkEmployeeRole()
    {

        if ($this->ion_auth->is_admin()) {
            return "admin";
        }

        //Get the login employee and check wheather s/he is a manager or not
        if ($this->ion_auth->logged_in()) {
            $empid = $this->ion_auth->user()->row()->empid;
        }

        // collect data in array for department head
        $args = array(
            'sTable' => 'dept',
            'fields' => "id",
            'where' => "manager = " . $empid,
            'countOrResult' => 'count'
        );

        // check the count
        $count = $this->getTablelist($args);

        if ((int)$count > 0) {
            return "department-head";
        } else {

            // collect data in array for division head
            $args = array(
                'sTable' => 'division',
                'fields' => "id",
                'where' => "manager = " . $empid,
                'countOrResult' => 'count'
            );

            // check the count
            $count = $this->getTablelist($args);

            if ((int)$count > 0) {
                return "division-head";
            } else {
                // collect data in array for region head
                $args = array(
                    'sTable' => 'region',
                    'fields' => "id",
                    'where' => "manager = " . $empid,
                    'countOrResult' => 'count'
                );

                // check the count
                $count = $this->getTablelist($args);

                if ((int)$count > 0) {
                    return "region-head";
                } else {
                    // collect data in array for branch head
                    $args = array(
                        'sTable' => 'branch',
                        'fields' => "id",
                        'where' => "manager = " . $empid,
                        'countOrResult' => 'count'
                    );

                    // check the count
                    $count = $this->getTablelist($args);

                    if ((int)$count > 0) {
                        return "branch-head";
                    } else {
                        // collect data in array for manager
                        $args = array(
                            'sTable' => 'emp',
                            'fields' => "id",
                            'where' => "manager = " . $empid . " and status = 'Active'",
                            'countOrResult' => 'count'
                        );

                        // check the count
                        $count = $this->getTablelist($args);

                        if ((int)$count > 0) {
                            return "manager";
                        } else {
                            return "employee";
                        }
                    }
                }
            }
        }
    }

    function get_apply_details($args)
    {
        $type = isset($args['type']) ? strlen(trim($args['type'])) === 0 ? "" : $args['type'] : "";
        $id = isset($args['id']) ? strlen(trim($args['id'])) === 0 ? 0 : $args['id'] : 0;

        $limit = isset($args['limit']) ? strlen(trim($args['limit'])) === 0 ? 0 : $args['limit'] : 0;

        if (strlen(trim($type)) === 0 && (int)$id === 0) {
            return 0;
        } else if ($type === "Leave") {
            $select = " concat_ws(' ', format(la.actualdays, 2), 'Day(s)', lt.leavetype, 'Leave', IF (la.transtype = 'CR', '(Credit)', '') ) details, ";
            $select .= " date_format(la.startdate, '%e-%b-%Y') as startdate, date_format(la.enddate, '%e-%b-%Y') as enddate, la.description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('leaves_applied as la', 'em.id = la.empid');
            $this->db->join('leave_type as lt', 'lt.id = la.leavetypeid');

            $data_args = array(
                'la.id' => $id
            );
        } else if ($type === "Travel") {
            $select = " concat_ws(' ', format(tr.noofdays, 2), 'Day(s)', 'to', tr.location) details, ";
            $select .= " date_format(tr.from_date, '%e-%b-%Y') as startdate, date_format(tr.to_date, '%e-%b-%Y') as enddate, tr.purpose as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('travel_request as tr', 'em.id = tr.empid');

            $data_args = array(
                'tr.id' => $id
            );
        } else if ($type === "Expense") {
            $select = " concat_ws(' ', 'Rs. ', amount, ' towards claim') details, ";
            $select .= " date_format(ex.from_date, '%e-%b-%Y') as startdate, date_format(ex.to_date, '%e-%b-%Y') as enddate, ex.purpose as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('expense as ex', 'em.id = ex.empid');
            $this->db->join('expense_type as et', 'et.id = ex.expense_type_id');

            $data_args = array(
                'ex.id' => $id
            );
        } else if ($type === "Attendance") {
            $select = " concat('applied for rationalize ', date_format(substr(ar.check_in,1,10), '%e-%b-%Y'), ' Check-In:', substr(ar.check_in,11,6), ' Check-Out:', substr(ar.check_out,11,6)) details, ";
            $select .= " date_format(ar.check_in, '%e-%b-%Y') as startdate, date_format(ar.check_out, '%e-%b-%Y') as enddate, ar.reason as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('attendance_request as ar', 'em.id = ar.empid');

            $data_args = array(
                'ar.id' => $id
            );
        } else if ($type === "Resignation") {
            $select = " concat('Resigned on ', date_format(rr.resign_date, '%e-%b-%Y'), ' Notice Period:', rr.approved_notice_period, ' Last Working Date:', date_format(rr.last_date, '%e-%b-%Y')) details, ";
            $select .= " date_format(rr.resign_date, '%e-%b-%Y') as startdate, date_format(rr.last_date, '%e-%b-%Y') as enddate, rr.reason as description";

            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('resignation_request as rr', 'em.id = rr.empid');

            $data_args = array(
                'rr.id' => $id
            );
        } else if ($type === "Goal Approval" || $type === "Goal Review") {
            $select = " date_format(gs.start_year, '%e-%b-%Y') as startdate, date_format(gs.end_year, '%e-%b-%Y') as enddate";

            $this->db->select($select, false);
            $this->db->from("goal_setup as gs");

            $data_args = array(
                'gs.id' => $id
            );
        } else if ($type === "Asset") {
            $select = " concat('', aa.asset_applied_count ,' no. of asset ', at.asset_type_name , ' ' , a.asset_name , ' requested on ', date_format(substr(aa.request_date,1,10), '%e-%b-%Y')) details, ";
            $this->db->select($select, false);
            $this->db->from("asset_applied as aa");
            $this->db->join('assets as a', 'a.id = aa.asset_id');
            $this->db->join('asset_type as at', 'at.id = aa.asset_type_id');
            $this->db->join('emp as e', 'e.id = aa.empid');

            $data_args = array(
                'aa.id' => $id
            );
        } else if ($type === "Project kpi") {
            $select = " concat('', p.name , ' whose start date is ' , date_format(substr(pk.start_date,1,10), '%e-%b-%Y'), ' and end date is ', date_format(substr(pk.end_date,1,10), '%e-%b-%Y')) details, ";
            $this->db->select($select, false);
            $this->db->from("pms_project_kpi as pk");
            $this->db->join('project as p', 'p.id = pk.project_id');
            $this->db->join('emp as e', 'e.id = pk.empid');

            $data_args = array(
                'pk.id' => $id
            );
        } else if ($type === "Loan") {
            $select = " concat('', lr.loan_type,lr.loan_amount,lr.no_of_installments,lr.monthly_installment,lr.rate_on_interest ) details, ";
            $this->db->select($select, false);
            $this->db->from("emp as em");
            $this->db->join('pay_loan_request as lr', 'em.id = lr.emp_id');

            $data_args = array(
                'lr.id' => $id
            );
        }

        if (count($data_args) > 0) {
            $this->db->where($data_args);

            if ((int)$limit > 0) {
                $this->db->limit($limit, 0);
            }

            return $this->db->get()->row();
        } else {
            return array();
        }

    }

    public function checkBranchWeekDays($args)
    {
        $empid = $args['empid'];

        $onlyHolidays = isset($args['onlyHolidays']) ? (strlen(trim($args['onlyHolidays'])) === 0 ? "No" : $args['onlyHolidays']) : "No";

        $empDataArgs = array(
            'table' => 'emp',
            'fields' => 'branch',
            'field_data' => $empid
        );

        $branch = ($this->checkCountforgetDataByPassingfield($empDataArgs) > 0) ? $this->getDataByPassingfield($empDataArgs)->branch : "";

        $branchArgs = array(
            'sTable' => 'week_days_branch',
            'sorting' => 'week_days_id asc',
            'where' => 'branch_id is null or branch_id = 0'
        );

        if (strlen(trim($branch)) > 0) {
            if ($onlyHolidays === "Yes") {
                $branchArgs = array_merge($branchArgs, array(
                    'where' => 'branch_id = ' . $branch . ' and is_holiday = 0'
                ));
            } else {
                $branchArgs = array_merge($branchArgs, array(
                    'where' => 'branch_id = ' . $branch
                ));
            }
            if ($this->checkCountforgetTablelist($branchArgs) === 0) {

                if ($onlyHolidays === "Yes") {
                    $branchArgs = array_merge($branchArgs, array(
                        'where' => '(branch_id is null or branch_id = 0) and is_holiday = 0'
                    ));
                } else {
                    $branchArgs = array_merge($branchArgs, array(
                        'where' => 'branch_id is null or branch_id = 0'
                    ));
                }
            }
        }

        $days = $this->getTablelist($branchArgs);

        return $days;
    }

    function isWeeklyOffForBranch($empid, $date, $what_to_return = "boolean")
    {
        $weekly_off = ($what_to_return === "boolean" ? FALSE : ($what_to_return === "number" ? 0 : FALSE));

        //collect weekdays
        $week_days = $this->getTablelist(array(
            'sTable' => "week_days"
        ));

        //loop through week days
        foreach ($week_days as $wd) {
            $week_days_list[$wd->id] = $wd->weekday;
        }

        //collect week days for employee
        $workingDays = $this->checkBranchWeekDays(array('empid' => $empid));
        // get day number
        $day_number = date("w", strtotime($date));

        //check if holiday on date and no of weekly offs
        if ((int)$workingDays[$day_number]->is_holiday === 0 && strlen(trim($workingDays[$day_number]->weekly_off_days)) > 0) {
            $list = array(1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth');
            $weekly_off_days = explode(",", trim($workingDays[$day_number]->weekly_off_days));

            //loop through weekly off days
            foreach ($weekly_off_days as $day) {
                //compare with date with weekly off days date
                if ($date === date("Y-m-d", strtotime("$list[$day] " . $week_days_list[$workingDays[$day_number]->week_days_id] . " of " . date('M', strtotime($date)) . " " . date('Y', strtotime($date))))) {
                    if ((int)$workingDays[$day_number]->half_day === 0) {
                        $weekly_off = ($what_to_return === "boolean" ? TRUE : ($what_to_return === "number" ? 1 : TRUE));
                    } else {
                        $weekly_off = ($what_to_return === "boolean" ? FALSE : ($what_to_return === "number" ? 1 : FALSE));
                    }
                    break;
                }
            }
        } else if ((int)$workingDays[$day_number]->is_holiday === 0) {
            $weekly_off = ($what_to_return === "boolean" ? TRUE : ($what_to_return === "number" ? 1 : TRUE));
        }

        //check for half day weekly off
        if (intval($this->is_half_day_weekly_off_applies($empid)) === 1) {
            if (intval($this->get_non_half_days($empid, $date)) === 1) {
                $weekly_off = ($what_to_return === "boolean" ? TRUE : ($what_to_return === "number" ? 1 : TRUE));
            }
        }

        return $weekly_off;
    }

    public function getEmployeeWeeklyOff($empid, $date)
    {
        $is_shift_enabled = $this->getTablelist(array(
            'sTable' => "discrete_attribute",
            'fields' => "attribute_value",
            'where' => "attribute_name = 'IS_SHIFT_ENABLED'",
            'countOrResult' => "row"
        ));

        $exp_date = explode("-", $date);
        $month = isset($exp_date[1]) ? $exp_date[1] : date("m");
        $year = isset($exp_date[0]) ? $exp_date[0] : date("Y");

        $roster_holidays = $this->check_if_roster_data_exist_for_date_range($empid, $month, $year);

        $roster_set = "0";
        if ($roster_holidays) {
            $roster_set = "1";
        }

        if ($roster_set === "0") {
            if (count($is_shift_enabled) > 0 && (int)$is_shift_enabled->attribute_value === 1) {
                $this->load->library("timezonelib");
                $this->timezonelib->getSetTimeZoneFromEMPId(array('empid' => $empid));

                $continue_old_shift = $this->commonmodel->getTablelist(array(
                    'fields' => "field_value",
                    'sTable' => "variable_config",
                    'where' => "field_name = 'continue_old_shift'",
                    'countOrResult' => "row"
                ));

                $result = $this->getTablelist(array(
                    'fields' => "sa.weekly_off",
                    'sTable' => "shift_assign_detail sad",
                    'joinlist' => array(
                        array(
                            'table' => "shift_assign sa",
                            'condition' => "sad.shift_assign_id = sa.id",
                            'type' => ""
                        )
                    ),
                    'where' => "(sad.empid = $empid and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $date . "', '%Y-%m-%d') <= sa.end_date) or
                                (sad.empid = $empid and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and (sa.end_date is null OR sa.end_date =  '0000-00-00'))",
                    'countOrResult' => "row"
                ));

                if (count($result) > 0) {
                    $dw = date('D', strtotime($date));

                    if (strripos($result->weekly_off, $dw) !== false) {
                        return 0; // weekly off.
                    } else {
                        return 1; // weekly day.
                    }
                } else {
                    if ($continue_old_shift->field_value === "Yes") {
                        $result = $this->getTablelist(array(
                            'fields' => "max(start_date) start_date",
                            'sTable' => "shift_assign_detail sad",
                            'joinlist' => array(
                                array(
                                    'table' => "shift_assign sa",
                                    'condition' => "sad.shift_assign_id = sa.id",
                                    'type' => ""
                                )
                            ),
                            'where' => "sad.empid = $empid",
                            'countOrResult' => "row"
                        ));

                        if (count($result) > 0) {
                            $result_weekly_off = $this->getTablelist(array(
                                'fields' => "sa.weekly_off",
                                'sTable' => "shift_assign_detail sad",
                                'joinlist' => array(
                                    array(
                                        'table' => "shift_assign sa",
                                        'condition' => "sad.shift_assign_id = sa.id",
                                        'type' => ""
                                    )
                                ),
                                'where' => "sad.empid = $empid and str_to_date('" . $result->start_date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $date . "', '%Y-%m-%d') >= str_to_date('" . $result->start_date . "', '%Y-%m-%d')",
                                'countOrResult' => "row"
                            ));

                            if (count($result_weekly_off) > 0) {
                                $dw = date('D', strtotime($date));

                                if (strripos($result_weekly_off->weekly_off, $dw) !== false) {
                                    return 0; // weekly off.
                                } else {
                                    return 1; // weekly day.
                                }
                            } else {
                                if ($this->isWeeklyOffForBranch($empid, $date)) {
                                    return 0; // weekly off.
                                } else {
                                    return 1; // weekly day.
                                }
                            }
                        } else {
                            if ($this->isWeeklyOffForBranch($empid, $date)) {
                                return 0; // weekly off.
                            } else {
                                return 1; // weekly day.
                            }
                        }
                    } else {
                        if ($this->isWeeklyOffForBranch($empid, $date)) {
                            return 0; // weekly off.
                        } else {
                            return 1; // weekly day.
                        }
                    }
                }
            } else {
                // check weekly off for branch
                if ($this->isWeeklyOffForBranch($empid, $date)) {
                    return 0; // weekly off.
                } else {
                    return 1; // weekly day.
                }
            }
        } else {
            $result = $this->getTablelist(array(
                'fields' => "sa.weekly_off",
                'sTable' => "shift_assign_detail sad",
                'joinlist' => array(
                    array(
                        'table' => "shift_assign sa",
                        'condition' => "sad.shift_assign_id = sa.id",
                        'type' => ""
                    )
                ),
                'where' => "(sad.empid = $empid and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $date . "', '%Y-%m-%d') <= sa.end_date) or
                                (sad.empid = $empid and str_to_date('" . $date . "', '%Y-%m-%d') >= sa.start_date and (sa.end_date is null OR sa.end_date =  '0000-00-00'))",
                'countOrResult' => "row"
            ));
            if (isset($result->weekly_off) && $result->weekly_off != "") {
                return 0; // weekly off.
            } else {
                return 1; // weekly day.
            }
        }
    }

    function getDefaultShiftWeeklyOff($empid, $date)
    {
        $result = $this->getTablelist(array(
            'fields' => "sm.weekly_off",
            'sTable' => "shift_master sm",
            'where' => "is_default = 1",
            'countOrResult' => "row"
        ));

        if (count($result) > 0) {
            $dw = date('D', strtotime($date));

            if (strripos($result->weekly_off, $dw) !== false) {
                return 0; // weekly off.
            } else {
                return 1; // weekly day.
            }
        } else {
            // check weekly off for branch
            if ($this->isWeeklyOffForBranch($empid, $date)) {
                return 0; // weekly off.
            } else {
                return 1; // weekly day.
            }
        }
    }

    public function getOverTimeData($empid, $check_in_time, $check_out_time, $working_hrs = "", $calculate_break_shift = "")
    {
        $overtime = "";
        $worked_hours = "";
        $overtime = "";
        $start_date = date("Y-m-d", strtotime($check_in_time));

        //Check if check out time is null then return 00:00 as overtime
        if (strlen($check_out_time) === 0 || strlen($check_in_time) === 0) {
            return "00:00";
        }

        $is_shift_enabled = $this->getTablelist(array(
            'sTable' => "discrete_attribute",
            'fields' => "attribute_value",
            'where' => "attribute_name = 'IS_SHIFT_ENABLED'",
            'countOrResult' => "row"
        ));

        if (count($is_shift_enabled) > 0 && (int)$is_shift_enabled->attribute_value === 1) {
            $this->load->library("timezonelib");
            $this->timezonelib->getSetTimeZoneFromEMPId(array('empid' => $empid));

            //First get the shift for an employyee for a particular date
            $result = $this->getTablelist(array(
                'fields' => "sm.start_time, sm.end_time, sm.is_break_shift, sm.working_hours",
                'sTable' => "shift_master sm",
                'joinlist' => array(
                    array(
                        'table' => "shift_assign sa",
                        'condition' => "sm.id = sa.shift_id",
                        'type' => ""
                    ),
                    array(
                        'table' => "shift_assign_detail sad",
                        'condition' => "sad.shift_assign_id = sa.id",
                        'type' => ""
                    )
                ),
                'where' => "sad.empid = $empid and sa.status = 'Active' and str_to_date('" . $start_date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $start_date . "', '%Y-%m-%d') <= sa.end_date",
                'countOrResult' => "row"
            ));

            if (count($result) > 0) {
                if (strlen($working_hrs) > 0) {
                    $working_hours = $working_hrs . ":00";
                } else {
                    $working_hours = $result->working_hours;
                }

                if (strpos($working_hours, ".") !== false) {
                    $working_hours = str_replace(".", ":", $working_hours);
                }

                //Check if the the shift assign to an employee is a break shift
                if ((int)$result->is_break_shift === 1 && $calculate_break_shift === "Yes") {
                    $worked_hours = $this->worked_hrs_for_break_shift($empid, $start_date);
                } else {
                    $worked_hours = $this->get_time_interval($check_out_time, $check_in_time);
                }

                $tmp_worked_hours = explode(":", $worked_hours);
                $tmp_working_hours = explode(":", $working_hours);

                if (((int)$tmp_worked_hours[0] > (int)$tmp_working_hours[0]) || ((int)$tmp_worked_hours[0] === (int)$tmp_working_hours[0]) && ((int)$tmp_worked_hours[1] > (int)$tmp_working_hours[1])) {
                    $overtime = $this->get_time_interval($worked_hours, $working_hours, true);
                } else {
                    $overtime = "00:00";
                }
            } else {
                //Get default shift details in case no shift is assign to the employee
                $result = $this->getTablelist(array(
                    'fields' => "sm.start_time, sm.end_time, sm.cut_off_time, sm.is_break_shift, sm.working_hours",
                    'sTable' => "shift_master sm",
                    'where' => "sm.is_default = 1",
                    'countOrResult' => "row"
                ));

                if (count($result) > 0) {
                    if (strlen($working_hrs) > 0) {
                        $working_hours = $working_hrs . ":00";
                    } else {
                        $working_hours = $result->working_hours;
                    }

                    if (strpos($working_hours, ".") !== false) {
                        $working_hours = str_replace(".", ":", $working_hours);
                    }

                    //Check if the the shift assign to an employee is a break shift
                    if ((int)$result->is_break_shift === 1 && $calculate_break_shift === "Yes") {
                        $worked_hours = $this->worked_hrs_for_break_shift($empid, $start_date);
                    } else {
                        $worked_hours = $this->get_time_interval($check_out_time, $check_in_time);
                    }

                    $tmp_worked_hours = explode(":", $worked_hours);
                    $tmp_working_hours = explode(":", $working_hours);

                    if (((int)$tmp_worked_hours[0] > (int)$tmp_working_hours[0]) || ((int)$tmp_worked_hours[0] === (int)$tmp_working_hours[0]) && ((int)$tmp_worked_hours[1] > (int)$tmp_working_hours[1])) {
                        $overtime = $this->get_time_interval($worked_hours, $working_hours, true);
                    } else {
                        $overtime = "00:00";
                    }

                } else {
                    //If no default shift is found then consider 8 hours as working hours for calculating overtime
                    if (strlen($working_hrs) > 0) {
                        $working_hours = $working_hrs . ":00";
                    } else {
                        $working_hours = "08:00";
                    }

                    $worked_hours = $this->get_time_interval($check_out_time, $check_in_time);

                    $tmp_worked_hours = explode(":", $worked_hours);
                    $tmp_working_hours = explode(":", $working_hours);

                    if (((int)$tmp_worked_hours[0] > (int)$tmp_working_hours[0]) || ((int)$tmp_worked_hours[0] === (int)$tmp_working_hours[0]) && ((int)$tmp_worked_hours[1] > (int)$tmp_working_hours[1])) {
                        $overtime = $this->get_time_interval($worked_hours, $working_hours, true);
                    } else {
                        $overtime = "00:00";
                    }
                }
            }
        } else {
            //If no default shift is found then consider 8 hours as working hours for calculating overtime
            if (strlen($working_hrs) > 0) {
                $working_hours = $working_hrs . ":00";
            } else {
                $working_hours = "08:00";
            }

            $worked_hours = $this->get_time_interval($check_out_time, $check_in_time);

            $tmp_worked_hours = explode(":", $worked_hours);
            $tmp_working_hours = explode(":", $working_hours);

            if (((int)$tmp_worked_hours[0] > (int)$tmp_working_hours[0]) || ((int)$tmp_worked_hours[0] === (int)$tmp_working_hours[0]) && ((int)$tmp_worked_hours[1] > (int)$tmp_working_hours[1])) {
                $overtime = $this->get_time_interval($worked_hours, $working_hours, true);
            } else {
                $overtime = "00:00";
            }
        }

        if (strlen($overtime) > 0) {
            return $overtime;
        } else {
            return "00:00";
        }
    }

    function reorder_elements($tablename, $pk_column, $pk_val, $no_in_seq_col, $start_pos, $end_pos, $args = array())
    {
        if ($start_pos < $end_pos) {
            $element_details = $this->getTablelist(array(
                'sTable' => $tablename,
                'where' => $no_in_seq_col . " between " . ($start_pos + 1) . " and " . $end_pos,
                'sorting' => $no_in_seq_col
            ));
            $start = $start_pos;
        } else if ($start_pos > $end_pos) {
            $element_details = $this->getTablelist(array(
                'sTable' => $tablename,
                'where' => $no_in_seq_col . " between " . $end_pos . " and " . ($start_pos - 1),
                'sorting' => $no_in_seq_col
            ));
            $start = $end_pos + 1;
        }

        if (count($element_details) > 0) {
            foreach ($element_details as $element) {
                $this->data_change(array(
                    'mode' => "Edit",
                    'table' => $tablename,
                    'id' => $element->id,
                    'add_user_info' => "Yes",
                    'tableData' => array(
                        $no_in_seq_col => $start
                    )
                ));
                $start = $start + 1;
            }
        }

        $this->data_change(array(
            'mode' => "Edit",
            'table' => $tablename,
            'id' => $pk_val,
            'add_user_info' => "Yes",
            'tableData' => array(
                $no_in_seq_col => $end_pos
            )
        ));

        return TRUE;
    }

    function between($x, $lim1, $lim2)
    {
        if ($lim1 === "Unlimited")
            $lim1 = 99999999999;

        if ($lim2 === "Unlimited")
            $lim2 = 99999999999;

        if ($lim1 < $lim2) {
            $lower = $lim1;
            $upper = $lim2;
        } else {
            $lower = $lim2;
            $upper = $lim1;
        }

        return ((($x >= $lower) && ($x <= $upper)) === true ? 1 : 0);
    }

    public function log_status($args)
    {
        $mode = $args["mode"];
        $id = isset($args["id"]) ? (strlen(trim($args["id"])) > 0 ? $args["id"] : 0) : 0;
        $table = $args["table"];
        $tableData = $args["tableData"];
//        $begin_trans = isset($args['begin_trans']) ? (strlen(trim($args['begin_trans'])) === 0 ? true : $args['begin_trans']) : true;
//        $end_trans = isset($args['end_trans']) ? (strlen(trim($args['end_trans'])) === 0 ? true : $args['end_trans']) : true;
        $echo = isset($args["echo"]) ? (strlen(trim($args["echo"])) > 0 ? $args["echo"] : "return") : "return";

        $log = $this->data_change(array(
            'mode' => $mode,
            'id' => $id,
            'table' => $table,
//            'begin_trans' => $begin_trans,
//            'end_trans' => $end_trans,
            'tableData' => $tableData
        ));

        if ($echo === "echo") {
            echo $log;
        } else if ($echo === "return") {
            return $log;
        }
    }

    function hoursToMinutes($hours)
    {
        $minutes = 0;
        if (strpos($hours, ':') !== false) {
            // Split hours and minutes.
            list($hours, $minutes) = explode(':', $hours);
        }
        return $hours * 60 + $minutes;
    }

    function minutesToHours($minutes)
    {
        $hours = (int)($minutes / 60);
        $minutes -= $hours * 60;
        return sprintf("%d:%02.0f", $hours, $minutes);
    }

    function round_number($number = 0, $mode = "Round", $number_upto = 2, $element_id = 0)
    {
        //Get settings for the element
        if (intval($element_id) > 0) {
            $element_detail = $this->getTablelist(array(
                'sTable' => "pay_element",
                'fields' => "round_mode, number_of_decimal",
                'where' => "id = $element_id",
                'countOrResult' => "row"
            ));

            if (!empty($element_detail) && isset($element_detail->round_mode)) {
                $mode = $element_detail->round_mode;
                $number_upto = $element_detail->number_of_decimal;
            }
        }

        if (strlen(trim($number_upto)) === 0)
            $number_upto = 0;

        switch ($mode) {
            case "None":
//                $number = bcdiv($number, 1, $number_upto);
                $number = explode(".", $number);
                return $number[0] . "." . (isset($number[1]) ? substr($number[1], 0, $number_upto) : str_pad("0", $number_upto, "0"));
            case "Round":
                $number = round((float)$number, $number_upto);
                return (float)$number;
            case "Round Up":
                if (strpos($number, ".") !== FALSE) {
                    $val = explode(".", $number);
                    if (intval($val[1]) > 0) {
                        $number = intval($val[0]) + 1;
                    }
                }
                
                return number_format((float)$number, $number_upto, ".", "");
            case "Round Down":
                $number = floor((float)$number);
                return number_format((float)$number, $number_upto, ".", "");
        }
    }

    public function getHolidayName($ids)
    {
        if (strlen(trim($ids)) === 0)
            return "";

        $HolidayName = $this->getTablelist(array(
            'sTable' => "holidaylist",
            'fields' => "replace(group_concat(concat(holiday, ' [', date_format(holidaydate, '%d-%m-%Y'), ']') SEPARATOR '~'), '~', ', ') holiday",
            'where' => "id in (" . $ids . ")",
            'countOrResult' => "row"
        ));

        return count($HolidayName) > 0 ? $HolidayName->holiday : "";
    }

    public function AddLeaveStatus($args)
    {
        if ($this->db->table_exists('emp_leaves_status')) {
            $args = array(
                'mode' => 'Add',
                'table' => 'emp_leaves_status',
                'add_user_info' => "Yes",
                'tableData' => array(
                    'empid' => $args["empid"],
                    'leave_id' => $args["leave_id"],
                    'status' => $args["status"],
                    'status_date' => date("Y-m-d"),
                    'amount' => $args["balance"],
                    'comments' => isset($args["comments"]) ? $args["comments"] : ""
                )
            );

            return $this->data_change($args);
        }
    }

    public function consolidate_list_of_tasks($isAdmin)
    {
        return array(
            'Leave' => $this->consolidate_list_of_leave($isAdmin),
            'Claim' => $this->consolidate_list_of_claim($isAdmin),
            'Travel' => $this->consolidate_list_of_travel($isAdmin),
            'Attendance' => $this->consolidate_list_of_attendance($isAdmin),
            'Resignation' => $this->consolidate_list_of_resignation($isAdmin),
            'Helpdesk' => $this->consolidate_list_of_helpdesk($isAdmin),
        );
    }

    function consolidate_list_of_leave($isAdmin)
    {
        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $where = "";
                $applied_where = "status in (0, 2)";
                $approved_where = "emp_approve_state = 'Active'";
                if ((int)$isAdmin === 0) {
                    $empid = $this->ion_auth->user()->row()->empid;
                    $where = " and empid = " . $empid;
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "leaves_applied",
                    'fields' => "id",
                    'where' => $applied_where . $where,
                    'countOrResult' => "count"
                )), "Leave Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "leaves_approval lp",
                    'fields' => "lp.id",
                    'where' => $approved_where . " and la.status in (0, 2) and lp.empid = " . $this->ion_auth->user()->row()->empid,
                    'joinlist' => array(
                        array(
                            'table' => "leaves_applied la",
                            'condition' => "la.id = lp.leaves_applied_id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Leave Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function consolidate_list_of_claim($isAdmin)
    {
        $empid = "";

        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $applied_where = "e.status in (0, 2)";
                $approved_where = "emp_approve_state = 'Active'";
                if ((int)$isAdmin === 0) {
                    $empid = " and e.empid = " . $this->ion_auth->user()->row()->empid;
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "expense e",
                    'fields' => "e.id",
                    'where' => $applied_where . $empid . " and " . $approved_where,
                    'joinlist' => array(
                        array(
                            'table' => "expense_approval ea",
                            'condition' => "ea.expense_applied_id = e.id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Claim Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "expense_approval ea",
                    'fields' => "ea.id",
                    'where' => $approved_where . " and e.status in (0, 2) and ea.empid = " . $this->ion_auth->user()->row()->empid,
                    'joinlist' => array(
                        array(
                            'table' => "expense e",
                            'condition' => "e.id = ea.expense_applied_id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Claim Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function consolidate_list_of_travel($isAdmin)
    {
        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $where = "";
                $applied_where = "status in (0, 2)";
                $approved_where = "emp_approve_state = 'Active'";
                if ((int)$isAdmin === 0) {
                    $empid = $this->ion_auth->user()->row()->empid;
                    $where = " and empid = " . $empid;
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "travel_request",
                    'fields' => "id",
                    'where' => $applied_where . $where,
                    'countOrResult' => "count"
                )), "Travel Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "travel_approval ta",
                    'fields' => "ta.id",
                    'where' => $approved_where . " and tr.status in (0, 2) and ta.empid = " . $this->ion_auth->user()->row()->empid,
                    'joinlist' => array(
                        array(
                            'table' => "travel_request tr",
                            'condition' => "tr.id = ta.travel_request_id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Travel Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function consolidate_list_of_attendance($isAdmin)
    {
        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $where = "";
                $applied_where = "status in (0, 2)";
                $approved_where = "emp_approve_state = 'Active'";
                if ((int)$isAdmin === 0) {
                    $empid = $this->ion_auth->user()->row()->empid;
                    $where = " and empid = " . $empid;
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "attendance_request",
                    'fields' => "id",
                    'where' => $applied_where . $where,
                    'countOrResult' => "count"
                )), "Attendance Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "attendance_approval aa",
                    'fields' => "aa.id",
                    'where' => $approved_where . " and ar.status in (0, 2) and aa.empid = " . $this->ion_auth->user()->row()->empid,
                    'joinlist' => array(
                        array(
                            'table' => "attendance_request ar",
                            'condition' => "ar.id = aa.attendance_request_id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Attendance Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function consolidate_list_of_resignation($isAdmin)
    {
        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $where = "";
                $applied_where = "status in (0, 2)";
                $approved_where = "emp_approve_state = 'Active'";
                if ((int)$isAdmin === 0) {
                    $empid = $this->ion_auth->user()->row()->empid;
                    $where = " and empid = " . $empid;
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "resignation_request",
                    'fields' => "id",
                    'where' => $applied_where . $where,
                    'countOrResult' => "count"
                )), "Resignation Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "resignation_approval ra",
                    'fields' => "ra.id",
                    'where' => $approved_where . " and rr.status in (0, 2) and ra.empid = " . $this->ion_auth->user()->row()->empid,
                    'joinlist' => array(
                        array(
                            'table' => "resignation_request rr",
                            'condition' => "rr.id = ra.resignation_request_id",
                            'type' => ""
                        )
                    ),
                    'countOrResult' => "count"
                )), "Resignation Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function consolidate_list_of_helpdesk($isAdmin)
    {
        if (class_exists('ion_auth')) {
            if ($this->ion_auth->logged_in()) {
                $where = "";
                $emp_query = "";
                $applied_where = "ticket_status in (1, 3)";

                if ((int)$isAdmin === 0) {
                    $empid = $this->ion_auth->user()->row()->empid;
                    $where = " and empid = " . $empid;

                    $this->load->model("ticketmodel");
                    $groups = $this->ticketmodel->getUsersGroups($this->ion_auth->user()->row()->id);
                    $emp_query = " and (assigned_to = " . $empid;

                    if (strlen(trim($groups)) > 0) {
                        $emp_query .= " or assigned_to in (" . $groups . ")";
                    }
                    $emp_query .= ")";
                }

                $my_apply_count = $this->convert_to_link($this->getTablelist(array(
                    'sTable' => "ticket",
                    'fields' => "id",
                    'where' => $applied_where . $where,
                    'countOrResult' => "count"
                )), "Helpdesk Applied", $isAdmin);

                $my_approval_count = $this->convert_to_link($this->getTablelist(array(
                    'fields' => 'id',
                    'sTable' => 'ticket',
                    'where' => $applied_where . $emp_query,
                    'countOrResult' => "count"
                )), "Helpdesk Approval", $isAdmin);

                return array($my_apply_count, $my_approval_count);
            }
        }
    }

    function convert_to_link($value, $to, $isAdmin)
    {
        switch ($to) {
            case "Leave Approval":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/leaves/approveleavedetails"><b>' . $value . "</b></a>";
                } else {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/leaves/approveleavedetails"><b>' . $value . "</b></a>";
                }
                break;
            case "Travel Approval":
                return '<a href="https://' . getDomain() . '.easyhrworld.com/travel/travel_approval"><b>' . $value . "</b></a>";
                break;
            case "Claim Approval":
                return '<a href="https://' . getDomain() . '.easyhrworld.com/expenses/expense_approval"><b>' . $value . "</b></a>";
                break;
            case "Attendance Approval":
                return '<a href="https://' . getDomain() . '.easyhrworld.com/attendance/attendance_approval"><b>' . $value . "</b></a>";
                break;
            case "Resignation Approval":
                return '<a href="https://' . getDomain() . '.easyhrworld.com/resignation/approval"><b>' . $value . "</b></a>";
                break;
            case "Helpdesk Approval":
                return '<a href="https://' . getDomain() . '.easyhrworld.com/tickets/assignedtickets"><b>' . $value . "</b></a>";
                break;
            case "Leave Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/leaves/history"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
            case "Travel Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/travel/travel_history"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
            case "Claim Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/expenses/expense_history"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
            case "Attendance Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/attendance/rationalize_history"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
            case "Resignation Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/resignation"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
            case "Helpdesk Applied":
                if ((int)$isAdmin === 0) {
                    return '<a href="https://' . getDomain() . '.easyhrworld.com/tickets/ticketlist"><b>' . $value . "</b></a>";
                } else {
                    return '<b>' . $value . "</b>";
                }
                break;
        }
    }

    public function addRemoveLeaves($empid, $leaveid, $state, $amount, $allow_leave_more_than_current_balance = "No", $negative_balance = 0)
    {
        $empArgs = array(
            'sTable' => 'emp_leaves',
            'where' => array(
                'empid' => $empid,
                'type' => $leaveid
            ),
            'fields' => 'id, balance',
            'countOrResult' => 'row'
        );
        $employees_balance = $this->getTablelist($empArgs);

        $balance = number_format(floatval($employees_balance->balance), 2);
        $actual_amount = 0;

        if ($state === "CR") {
            $actual_amount = $balance + number_format(floatval($amount), 2);
        } else if ($state === "DR") {
            if ($allow_leave_more_than_current_balance === "No") {
                if ($balance - number_format(floatval($amount), 2) <= 0) {
                    $actual_amount = 0;
                } else {
                    $actual_amount = $balance - number_format(floatval($amount), 2);
                }
            } else {
                $actual_amount = floatval($balance) - floatval($amount);
                $actual_amount = floatval($actual_amount) < (floatval($negative_balance) * -1) ? (floatval($negative_balance) * -1) : $actual_amount;
            }
        }

        $emp_leaves = array(
            'mode' => "Edit",
            'id' => $employees_balance->id,
            'table' => 'emp_leaves',
            'tableData' => array(
                'balance' => number_format(floatval($actual_amount), 2)
            )
        );

        $this->data_change($emp_leaves);

        $this->AddLeaveStatus(array(
            'empid' => $empid,
            'leave_id' => $leaveid,
            'status' => $state,
            'balance' => number_format((float)$amount, 2)
        ));
    }

    public function isLeaveLWP($id)
    {
        return $this->getTablelist(array(
            'sTable' => "leave_type",
            'fields' => "is_lwp",
            'where' => "id = " . $id,
            'countOrResult' => "row"
        ))->is_lwp;
    }

    public function getQueryCount($query_string)
    {
        $query = $this->db->query($query_string);
        return $query->num_rows();
    }

    public function get_date_range($date_range)
    {
        $this->load->library("timezonelib");
        $this->timezonelib->getSetTimeZone();

        $curdate = date('Y-m-d');
        $start_date = "";
        $end_date = "";

        switch (strtolower($date_range)) {
            case "today":
                $start_date = $curdate;
                $end_date = $curdate;
                break;
            case "yesterday":
                $start_date = date('Y-m-d', strtotime($curdate . '-1 day'));
                $end_date = date('Y-m-d', strtotime($curdate . '-1 day'));
                break;
            case "last seven days":
                $start_date = date('Y-m-d', strtotime($curdate . '-7 day'));
                $end_date = $curdate;
                break;
            case "last week":
                $start_date = date('Y-m-d', strtotime('-1  Monday'));
                $end_date = date('Y-m-d', strtotime("+6 day", strtotime('-1  Monday')));
                break;
            case "last two weeks":
                $start_date = date('Y-m-d', strtotime('-2  Monday'));
                $end_date = date('Y-m-d', strtotime("+13 day", strtotime('-2  Monday')));
                break;
            case "last three weeks":
                $start_date = date('Y-m-d', strtotime('-3  Monday'));
                $end_date = date('Y-m-d', strtotime("+20 day", strtotime('-3  Monday')));
                break;
            case "last month":
                $start_date = date("Y-m-01", strtotime("first day of previous month"));
                $end_date = date("Y-m-t", strtotime("first day of previous month"));
                break;
            case "last quarter":
                $q = ceil((int)date('m') / 3) - 1;
                $y = date('Y');

                if ($q <= 0) {
                    $q = 4;
                    $y = $y - 1;
                }

                $months_start = array('01', '04', '07', '10');
                $months_end = array('03', '06', '09', '12');

                $start_date = date($y . '-' . $months_start[$q - 1] . '-01');
                $end_date = date($y . '-' . $months_end[$q - 1] . '-t');
                break;

            case "last year":
                $y = (int)date('Y') - 1;
                $start_date = date($y . '-01-01');
                $end_date = date($y . '-12-31');
        }

        return array('startdate' => $start_date, 'enddate' => $end_date);
    }

    public function get_time_interval($end_date, $start_date, $is_time = false)
    {
        if ($is_time) {
            $hrs = "24:00";
            if ($end_date > $hrs) {
                //Get extra time above 24 hours
                $end_dt = explode(":", $end_date);
                if (isset($end_dt[0]) && strlen($end_dt[0]) > 0) {
                    $extra_hrs = (int)$end_dt[0] - 24;
                } else {
                    $extra_hrs = (int)$end_date - 24;
                }

                if (isset($end_dt[1]) && strlen($end_dt[1]) > 0) {
                    $extra_hrs = $extra_hrs . ":" . $end_dt[1] . ":00";
                } else {
                    $extra_hrs = $extra_hrs . ":00:00";
                }

                //Get the difference between time
                $date_a = new DateTime($hrs);
                $date_b = new DateTime($start_date);
                $interval = date_diff($date_a, $date_b);
                $remaning_time = $interval->format('%H:%i:%s');

                //Add extra time above 24 and the difference
                $overtime = $this->sum_the_time($extra_hrs, $remaning_time);

                return $overtime;
            } else {
                $end_dt = explode(":", $end_date);
                $start_dt = explode(":", $start_date);

                $end_dt_hrs_in_sec = 0;
                if (isset($end_dt[0]) && strlen($end_dt[0]) > 0) {
                    $end_dt_hrs_in_sec = $end_dt[0] * 3600;
                }

                $end_dt_mins_in_sec = 0;
                if (isset($end_dt[1]) && strlen($end_dt[1]) > 0) {
                    $end_dt_mins_in_sec = $end_dt[1] * 60;
                }

                $end_dt_sec = (int)$end_dt_hrs_in_sec + (int)$end_dt_mins_in_sec;

                $start_dt_hrs_in_sec = 0;
                if (isset($start_dt[0]) && strlen($start_dt[0]) > 0) {
                    $start_dt_hrs_in_sec = $start_dt[0] * 3600;
                }

                $start_dt_mins_in_sec = 0;
                if (isset($start_dt[1]) && strlen($start_dt[1]) > 0) {
                    $start_dt_mins_in_sec = $start_dt[1] * 60;
                }

                $start_dt_sec = (int)$start_dt_hrs_in_sec + (int)$start_dt_mins_in_sec;

                $diff_in_sec = (int)$end_dt_sec - (int)$start_dt_sec;
                $overtime = gmdate("H:i", $diff_in_sec);
                return $overtime;
            }
        } else {
            $date_a = new DateTime($end_date);
            $date_b = new DateTime($start_date);

            $interval = date_diff($date_a, $date_b);
            return $interval->format('%H:%i');
        }
    }

    public function worked_hrs_for_break_shift($empid, $start_date)
    {
        $result = $this->getTablelist(array(
            'fields' => "date_format(a.start_time, '%d-%m-%Y %H:%i') as start_time, date_format(a.end_time, '%d-%m-%Y %H:%i') as end_time",
            'sTable' => "emp_attendance a",
            'where' => "a.empid = " . $empid . " and start_dt = '" . $start_date . "' and end_tm is not null"
        ));

        $total_hrs = "0:00:00";
        $hrs = 0;

        foreach ($result as $row) {
            $temp_hours = $this->get_time_interval($row->end_time, $row->start_time);
            $temp_hours = $temp_hours . ":00";
            $current_day_hrs = $this->sum_the_time($total_hrs, $temp_hours);
            $total_hrs = $current_day_hrs . ":00";
        }

        return $total_hrs;
    }

    public function set_emp_current_info_for_payroll($emp_id, $Pay_salary_register_id)
    {
        $sql = "concat_ws(' ', firstname, middlename, lastname) as ename, e.empno, e.designation, e.dateofjoin, e.gender, e.pannumber,
                    e.office_city, e.office_state, e.PFNumber, d.deptname, eb.accountno, eb.name as bank_name, b.branch, dv.division,
                    et.employment_type, g.grade";

        if ((int)$this->check_for_column_exists("emp_userdata", "uan_no") === 1) {
            $sql = $sql . ", eu.uan_no";
        }

        if ((int)$this->check_for_column_exists("emp_userdata", "esic") === 1) {
            $sql = $sql . ", eu.esic";
        }

        $emp_detail = $this->getTablelist(array(
            'sTable' => "emp e",
            'fields' => $sql,
            'joinlist' => array(
                array(
                    'table' => "dept d",
                    'condition' => "e.department = d.id",
                    'type' => "left"
                ),
                array(
                    'table' => "emp_userdata eu",
                    'condition' => "e.id = eu.empid",
                    'type' => "left"
                ),
                array(
                    'table' => "emp_bank eb",
                    'condition' => "e.id = eb.empid",
                    'type' => "left"
                ),
                array(
                    'table' => "branch b",
                    'condition' => "b.id = e.branch",
                    'type' => "left"
                ),
                array(
                    'table' => "division dv",
                    'condition' => "dv.id = e.division",
                    'type' => "left"
                ),
                array(
                    'table' => "employment_type et",
                    'condition' => "et.id = e.job_status",
                    'type' => "left"
                ),
                array(
                    'table' => "grade g",
                    'condition' => "g.id = e.grade",
                    'type' => "left"
                )
            ),
            'where' => "e.id = " . $emp_id,
            'countOrResult' => "row"
        ));

        $arr_data = array();

        if (count($emp_detail) > 0) {
            foreach ($emp_detail as $index => $row) {
                $arr_data[] = array(
                    'pay_salary_register_id' => $Pay_salary_register_id,
                    'field_name' => $index,
                    'field_value' => $row
                );

            }
        }

        //Get current leave balance
        $leave_balance = $this->get_emp_leave_balance($emp_id);
        $arr_data[] = array(
            'pay_salary_register_id' => $Pay_salary_register_id,
            'field_name' => 'leave_balance',
            'field_value' => $leave_balance
        );

        if (count($arr_data) > 0) {
            $emp_elements = array(
                'table' => "pay_emp_detail",
                'data' => $arr_data,
                'begin_trans' => false,
                'end_trans' => false
            );

            $this->commonmodel->insert_batch($emp_elements);
        }
    }

    function check_for_column_exists($table_name, $column_name)
    {
        $sql = "SHOW COLUMNS FROM `$table_name` LIKE '$column_name'";
        $query = $this->db->query($sql);
        $result = $query->result();

        if ($result == null) {
            return 0;
        }

        return 1;
    }

    function check_if_roster_data_exist_for_date_range($empid, $month, $year)
    {
        $start_date = $year . "-" . $month . "-01";
        $last_day = date('t', strtotime($start_date));
        $end_date = $year . "-" . $month . "-" . $last_day;

        $this->db->select("sa.id");
        $this->db->from("shift_assign sa");
        $this->db->join("shift_assign_detail sad", "sa.id = sad.shift_assign_id");
        $this->db->where("date_format(sa.start_date, '%Y-%m-%d') between '" . $start_date . "' and '" . $end_date . "'");
        $this->db->where("sad.empid", $empid);

        $query = $this->db->get();
        $result = $query->result();

        if (count($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function send_notification_to_next_approver($args)
    {
        $fk_field = "";
        switch ($args["table"]) {
            case "travel_request":
                $fk_field = "travel_request_id";
                break;
            case "expense":
                $fk_field = "expense_applied_id";
                break;
            case "leaves_applied":
                $fk_field = "leaves_applied_id";
                break;
            case "attendance_request":
                $fk_field = "attendance_request_id";
                break;
            case "resignation_request":
                $fk_field = "resignation_request_id";
                break;
            case "recruit_new_jobs":
                $fk_field = "recruit_new_jobs_id";
                break;
            case "ticket":
                $fk_field = "ticket_id";
                break;
            case "employee_transfer":
                $fk_field = "employee_transfer_id";
                break;
            case "asset_applied":
                $fk_field = "asset_applied_id";
                break;
            case "pms_project_kpi_task":
                $fk_field = "project_kpi_task_id";
                break;
            case "pay_loan_request":
                $fk_field = "loan_request_id";
                break;
            case "pay_loan_prepayment_request":
                $fk_field = "loan_prepayment_request_id";
                break;
        }

        $this->load->library('commonlib');

        //Get employee id of the next approver
        $next_approver = $this->getTablelist(array(
            'sTable' => $args["base_table"],
            'fields' => "empid",
            'where' => "$fk_field = " . $args["id"] . " and approved_empid is null and emp_approve_state = 'Active' and number_in_sequence > 1",
            'countOrResult' => "row"
        ));

        if (isset($next_approver->empid) && (int)($next_approver->empid) > 0) {
            $request_type = explode(" ", $args["request_type"]);

            //Check for deligation
            $new_manager = $this->commonlib->checkIfDelegationIsOn(array('empid' => $next_approver->empid, 'task' => strtolower($request_type[0])));

            $this->load->library('employeelib');

            if ((int)$next_approver->empid !== (int)$new_manager) {
                $eData = $this->employeelib->getEmployeeDetails($new_manager);
            } else {
                $eData = $this->employeelib->getEmployeeDetails($next_approver->empid);
            }

            $email_args = array(
                'approval_type' => $request_type[0],
                'data' => $eData,
                'empid' => $args['empid'],
                'id' => $args["id"]
            );

            $this->commonlib->sendEmail($email_args);
        }
    }

    public function leave_applicable_to_emp($emp_id, $leave_type_id)
    {
        $is_leave_applicable = 0;

        //Get employee details
        $emp_detail = $this->getTablelist(array(
            'sTable' => 'emp',
            'fields' => 'id, gender, marital_status',
            'where' => 'id = ' . $emp_id,
            'countOrResult' => "row"
        ));

        if (isset($emp_detail) && (int)$emp_detail->id > 0) {
            //Get leave type list
            $leave_type = $this->getTablelist(array(
                'sTable' => 'leave_type',
                'fields' => 'id, gender, apply_to',
                'where' => 'id = ' . $leave_type_id,
                'countOrResult' => "row"
            ));

            if (isset($leave_type) && (int)$leave_type->id > 0) {
                if ($leave_type->apply_to === "All" && $leave_type->gender === "All") {
                    $is_leave_applicable = 1;
                } elseif ($leave_type->apply_to !== "All" && $leave_type->gender === "All") {
                    if ($leave_type->apply_to === $emp_detail->marital_status) {
                        $is_leave_applicable = 1;
                    }
                } elseif ($leave_type->apply_to === "All" && $leave_type->gender !== "All") {
                    if ($leave_type->gender === $emp_detail->gender) {
                        $is_leave_applicable = 1;
                    }
                } else {
                    if ($leave_type->apply_to === $emp_detail->marital_status && $leave_type->gender === $emp_detail->gender) {
                        $is_leave_applicable = 1;
                    }
                }
            }
        }

        return $is_leave_applicable;
    }

    public function is_leave_type_auto_credit($leave_type_id)
    {
        $leave_type = $this->getTablelist(array(
            'sTable' => 'leave_type',
            'fields' => 'auto_credit',
            'where' => 'id = ' . $leave_type_id,
            'countOrResult' => "row"
        ));

        return $leave_type->auto_credit;
    }

    public function getProfileList()
    {
        return $this->getTablelist(array(
            'sTable' => 'profile',
            'fields' => 'id, profile',
            'where' => 'is_active = 1 and profile not in ("ADMINISTRATOR", "EMPLOYEE")'
        ));
    }

    public function sum_the_time($time1, $time2)
    {
        $times = array($time1, $time2);
        $seconds = 0;
        foreach ($times as $time) {
            list($hour, $minute, $second) = explode(':', $time);
            $seconds += $hour * 3600;
            $seconds += $minute * 60;
            $seconds += $second;
        }
        $hours = floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;
        return $hours . ":" . $minutes;
        //return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function make_notification_all_as_read($args)
    {
        $count_args = array(
            'countOrResult' => "count",
            'sTable' => "notification_employee_data",
            'where' => $args
        );

        $rowCount = $this->getTablelist($count_args);

        if ((int)$rowCount === 0) {
            $args = array(
                'mode' => "Add",
                'table' => "notification_employee_data",
                'tableData' => array(
                    'emp_id' => $args['emp_id'],
                    'notification_id' => $args['notification_id']
                )
            );
            $this->commonmodel->data_change($args);
        }

        return true;
    }

    public function get_shift_for_employee($empid, $chk_date)
    {
        $assign_shift_id = 0;
        $start_time = "";
        $end_time = "";
        $over_night_shift = "No";
        $half_day_after = "";

        $continue_old_shift = $this->commonlib->get_config_variable("ATTENDANCE", "continue_old_shift");

        //First Check if shift is enabled
        $is_shift_enabled = $this->commonmodel->getTablelist(array(
            'sTable' => "discrete_attribute",
            'fields' => "attribute_value",
            'where' => "attribute_name = 'IS_SHIFT_ENABLED'",
            'countOrResult' => "row"
        ));

        if ((int)$is_shift_enabled->attribute_value > 0) {
            //Get the shift assign to an employee using roster
            $shift_detail = $this->commonmodel->getTablelist(array(
                'fields' => "sm.id, sm.start_time, sm.end_time, sm.half_day_after",
                'sTable' => "shift_master sm",
                'joinlist' => array(
                    array(
                        "table" => "shift_assign sa",
                        "condition" => "sa.shift_id = sm.id",
                        "type" => ""
                    ),
                    array(
                        "table" => "shift_assign_detail sad",
                        "condition" => "sad.shift_assign_id = sa.id",
                        "type" => ""
                    )
                ),
                'where' => "sad.empid = " . $empid . " and sa.start_date = '" . $chk_date . "'",
                'countOrResult' => "row"
            ));

            if (isset($shift_detail->id) && (int)$shift_detail->id > 0) {
                $assign_shift_id = $shift_detail->id;
                $start_time = $shift_detail->start_time;
                $end_time = $shift_detail->end_time;
                $half_day_after = $shift_detail->half_day_after;

                if ($shift_detail->end_time < $shift_detail->start_time) {
                    $over_night_shift = "Yes";
                }
            } else {
                //check if contnue old shift is set to yes
                if ($continue_old_shift === "Yes") {
                    //Get the last shift assign to an employee
                    $shift_detail = $this->commonmodel->getTablelist(array(
                        'fields' => "sm.id, sm.start_time, sm.end_time, sm.half_day_after",
                        'sTable' => "shift_master sm",
                        'joinlist' => array(
                            array(
                                "table" => "shift_assign sa",
                                "condition" => "sa.shift_id = sm.id",
                                "type" => ""
                            ),
                            array(
                                "table" => "shift_assign_detail sad",
                                "condition" => "sad.shift_assign_id = sa.id",
                                "type" => ""
                            )
                        ),
                        'where' => "sad.empid = " . $empid,
                        'sorting' => "sad.id desc",
                        'countOrResult' => "row"
                    ));

                    if (isset($shift_detail->id) && (int)$shift_detail->id > 0) {
                        $assign_shift_id = $shift_detail->id;
                        $start_time = $shift_detail->start_time;
                        $end_time = $shift_detail->end_time;
                        $half_day_after = $shift_detail->half_day_after;

                        if ($shift_detail->end_time < $shift_detail->start_time) {
                            $over_night_shift = "Yes";
                        }
                    }

                } else {
                    //Get the default shift
                    $shift_master = $this->commonmodel->getTablelist(array(
                        'fields' => "sm.id, sm.start_time, sm.end_time, sm.half_day_after",
                        'sTable' => "shift_master sm",
                        'where' => "sm.is_default = 1",
                        'countOrResult' => "row"
                    ));

                    if (count($shift_master) > 0) {
                        $assign_shift_id = $shift_master->id;
                        $start_time = $shift_master->start_time;
                        $end_time = $shift_master->end_time;
                        $half_day_after = $shift_master->half_day_after;

                        if ($shift_master->end_time < $shift_master->start_time) {
                            $over_night_shift = "Yes";
                        }
                    }
                }
            }
        }

        $args = array(
            'assign_shift_id' => $assign_shift_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'over_night_shift' => $over_night_shift,
            'half_day_after' => $half_day_after
        );

        return $args;
    }

    public function get_holidays_from_coming_months($month_arr, $employee)
    {
        $holidays = array();

        //Get emp details
        $emp_details = $this->commonmodel->getTablelist(array(
            'fields' => "department, division, region, branch",
            'sTable' => "emp",
            'where' => "id = $employee",
            'countOrResult' => "row"
        ));

        if (count($month_arr) > 0) {
            foreach ($month_arr as $month) {
                if (count($holidays) < 5) {
                    //Get the roster data for current month
                    $roster_details = $this->get_roster_details($month, $employee);
                    if (count($roster_details) > 0) {

                        //If roster is set then get the holiday in current month
                        foreach ($roster_details as $result) {
                            $holiday = isset($result->holidays) ? $result->holidays : "";
                            if (isset($holiday) && $holiday !== "") {
                                $this->db->select("distinct hl.holiday, DATE_FORMAT(hl.holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(hl.holidaydate, '%Y-%m-%d') as hldy_date", false);
                                $this->db->from("holidaylist hl");
                                $this->db->join("holidaylist_criteria_data hlcd", "hl.id = hlcd.holidaylist_id");
                                $this->db->where("hlcd.id", $holiday);
                                $this->db->where("hl.is_roster", "1");
                                $this->db->order_by("hl.holidaydate asc");

                                $query = $this->db->get();
                                $holidays[] = $query->row();
                            }
                        }
                    } else {
                        //If roster is not set then get the holiday from setting for current month for that employee
                        $holidays = $this->get_setting_holiday_for_emp($emp_details, $employee, $month, $holidays);
                    }
                }
            }
        }

        return $holidays;
    }

    public function get_roster_details($month, $employee)
    {
        $this->db->select("sa.id as shift_assign_id,sa.holidays", false);
        $this->db->from("shift_assign sa");
        $this->db->join("shift_assign_detail sad", "sa.id = sad.shift_assign_id");
        $this->db->where("date_format(sa.start_date, '%Y-%m-%d') between '" . $month['first_day'] . "' and '" . $month['last_day'] . "'");
        $this->db->where("sad.empid", $employee);
        $query = $this->db->get();
        $results = $query->result();

        return $results;
    }

    public function get_setting_holiday_for_emp($emp_details, $empid, $month, $holidays)
    {
        $where = "date_format(hl.holidaydate, '%Y-%m-%d') between '" . $month['first_day'] . "' and '" . $month['last_day'] . "' AND (hlcd.apply_to = 'employee' AND criteria_id = '" . $empid . "' OR hlcd.apply_to = 'department' AND criteria_id = '" . $emp_details->department . "' OR hlcd.apply_to = 'region' AND criteria_id = '" . $emp_details->region . "' OR  hlcd.apply_to = 'branch' AND criteria_id = '" . $emp_details->branch . "' OR hlcd.apply_to = 'all_employees')";
        $this->db->select("distinct hl.holiday, DATE_FORMAT(hl.holidaydate, '%D %b') as hdate, if(optional = 'Yes', '(optional)', '') as optional, DATE_FORMAT(hl.holidaydate, '%Y-%m-%d') as hldy_date", false);
        $this->db->from("holidaylist hl");
        $this->db->join("holidaylist_criteria_data hlcd", "hl.id = hlcd.holidaylist_id");
        $this->db->where($where);
        $this->db->order_by("hl.holidaydate asc");
        $this->db->limit(5, 0);

        $query = $this->db->get();
        $results = $query->result();

        if (count($results > 0)) {
            foreach ($results as $res) {
                if (count($holidays) < 5) {
                    $holidays[] = $res;
                }
            }
        }

        return $holidays;
    }

    public function get_employee_ctc_elements($pessid, $pay_groups_id)
    {
        $pay_elements = $this->commonmodel->getTablelist(array(
            'sTable' => "pay_element",
            'fields' => "id, element"
        ));

        foreach ($pay_elements as $pay_element) {
            $element[$pay_element->id] = $pay_element->element;
        }

        $pay_emp_salary_structure = $this->commonmodel->getTablelist(array(
            'sTable' => "pay_emp_salary_structure",
            'fields' => "ctc",
            'where' => "id = " . $pessid,
            'countOrResult' => "row"
        ));

        $pay_emp_salary_structure_detail = $this->commonmodel->getTablelist(array(
            'sTable' => "pay_emp_salary_structure_detail",
            'where' => "pay_emp_salary_structure_id = " . $pessid
        ));

        $pay_emp_salary_structure_detail = json_decode(json_encode($pay_emp_salary_structure_detail), true);

        $group_data = $this->commonmodel->getTablelist(array(
            'fields' => "pe.id, pe.element, pe.element_label, pge.element_type, pge.element_base, pge.element_value, pe1.element as base_element",
            'sTable' => "pay_group_elements pge",
            'joinlist' => array(
                array(
                    'table' => "pay_element pe",
                    'condition' => "pe.id = pge.pay_element_id",
                    'type' => ""
                ),
                array(
                    'table' => "pay_element pe1",
                    'condition' => "pe1.id = pge.element_base",
                    'type' => "left"
                )
            ),
            'where' => "pge.pay_groups_id = " . $pay_groups_id . " and pe.status = 'Active'
                        and pe.element not in ('Income Tax', 'Professional Tax')",
            'sorting' => "pge.num_in_seq asc"
        ));

        $salary_structure = array();
        foreach ($group_data as $group) {
            if ($group->element === "CTC") {
                $salary_structure[$group->element] = $pay_emp_salary_structure->ctc;
            } else {
                $found_data = $this->commonmodel->array_search($pay_emp_salary_structure_detail, $group->id, "pay_element_id");
                if ($group->element_type === "Fixed Amount") {
                    if ((int)$found_data === 0) {
                        $salary_structure[$group->element] = $group->element_value;
                    } else {
                        $salary_structure[$group->element] = $pay_emp_salary_structure_detail[(int)$this->commonmodel->array_search_return_key($pay_emp_salary_structure_detail, $group->id, "pay_element_id")]["element_value"];
                    }
                }

                if ($group->element_type === "Percentage") {
                    if ((int)$found_data === 0) {
                        $salary_structure[$group->element] = ($salary_structure[$element[$group->element_base]] * $group->element_value) / 100;
                    } else {
                        $salary_structure[$group->element] = $pay_emp_salary_structure_detail[(int)$this->commonmodel->array_search_return_key($pay_emp_salary_structure_detail, $group->id, "pay_element_id")]["element_value"];
                    }
                }

                if ($group->element_type === "Formula") {
                    $salary_structure[$group->element] = "";
                }
            }
        }

        foreach ($group_data as $group) {
            $found_data = $this->commonmodel->array_search($pay_emp_salary_structure_detail, $group->id, "pay_element_id");
            if ($group->element_type === "Formula") {
                if ((int)$found_data === 0) {
                    $var = $group->element_value;
                    foreach ($group_data as $search_group) {
                        if (strpos($var, $search_group->element) !== FALSE) {
                            $var = str_replace("@" . $search_group->element, $salary_structure[$search_group->element], $var);
                        }
                    }

                    try {
                        if (strpos($var, "@") === false) {
                            $salary_structure[$group->element] = eval("return $var;");
                        } else {
                            $salary_structure[$group->element] = 0;
                        }
                    } catch (Exception $e) {
                        $salary_structure[$group->element] = 0;
                    }
                } else {
                    $salary_structure[$group->element] = $pay_emp_salary_structure_detail[(int)$this->commonmodel->array_search_return_key($pay_emp_salary_structure_detail, $group->id, "pay_element_id")]["element_value"];
                }
            }
        }
        return $salary_structure;
    }

    public function getShiftAssignedToEmployee($empid, $date)
    {
        $this->db->select("sm.name, sm.is_break_shift, sm.flexible_time, sm.start_time, sm.working_hours, sm.half_time, sm.end_time", false);
        $this->db->from("shift_assign_detail sad");
        $this->db->join("shift_assign sa", "sad.shift_assign_id = sa.id");
        $this->db->join("shift_master sm", "sa.shift_id = sm.id");
        $this->db->where("(sad.empid = $empid and '$date' >= sa.start_date and '$date' <= sa.end_date)");
        $this->db->or_where("(sad.empid = $empid and '$date' >= sa.start_date and (sa.end_date is null OR sa.end_date =  '0000-00-00'))");

        $result = $this->db->get()->row();

        $shift = array(
            'name' => "",
            'is_break_shift' => 0,
            'flexible_time' => 1,
            'start_time' => "00:00",
            'working_hours' => "00:00",
            'half_time' => "00:00",
            'end_time' => "00:00"
        );

        if (!empty($result) > 0) {
            $shift = array(
                'name' => $result->name,
                'is_break_shift' => $result->is_break_shift,
                'flexible_time' => $result->flexible_time,
                'start_time' => $result->start_time,
                'working_hours' => $result->working_hours,
                'half_time' => $result->half_time,
                'end_time' => $result->end_time
            );
        } else {
            $continue_old_shift = $this->commonmodel->getTablelist(array(
                'fields' => "field_value",
                'sTable' => "variable_config",
                'where' => "field_name = 'continue_old_shift'",
                'countOrResult' => "row"
            ));

            if ($continue_old_shift->field_value === "Yes") {
                $last_shift = $this->attendancemodel->get_last_shift_assigned($empid);

                if (isset($last_shift) && count($last_shift) > 0) {
                    $shift = array(
                        'name' => $last_shift->name,
                        'is_break_shift' => $last_shift->is_break_shift,
                        'flexible_time' => $last_shift->flexible_time,
                        'start_time' => $last_shift->start_time,
                        'working_hours' => $last_shift->working_hours,
                        'half_time' => $last_shift->half_time,
                        'end_time' => $last_shift->end_time
                    );
                } else {
                    //Get default shift details in case no shift is assign to the employee
                    $result = $this->commonmodel->getTablelist(array(
                        'fields' => "sm.name, sm.is_break_shift, sm.flexible_time, sm.start_time, sm.working_hours, sm.half_time, sm.end_time",
                        'sTable' => "shift_master sm",
                        'where' => "sm.is_default = 1",
                        'countOrResult' => "row"
                    ));

                    if (count($result) > 0) {
                        $shift = array(
                            'name' => $result->name,
                            'is_break_shift' => $result->is_break_shift,
                            'flexible_time' => $result->flexible_time,
                            'start_time' => $result->start_time,
                            'working_hours' => $result->working_hours,
                            'half_time' => $result->half_time,
                            'end_time' => $result->end_time
                        );
                    }
                }
            } else {
                //Get default shift details in case no shift is assign to the employee
                $result = $this->commonmodel->getTablelist(array(
                    'fields' => "sm.name, sm.is_break_shift, sm.flexible_time, sm.start_time, sm.working_hours, sm.half_time, sm.end_time",
                    'sTable' => "shift_master sm",
                    'where' => "sm.is_default = 1",
                    'countOrResult' => "row"
                ));

                if (count($result) > 0) {
                    $shift = array(
                        'name' => $result->name,
                        'is_break_shift' => $result->is_break_shift,
                        'flexible_time' => $result->flexible_time,
                        'start_time' => $result->start_time,
                        'working_hours' => $result->working_hours,
                        'half_time' => $result->half_time,
                        'end_time' => $result->end_time
                    );
                }
            }
        }

        return $shift;
    }

    public function getEmployeeInOutByDate($empid, $date)
    {
        $result = $this->commonmodel->getTablelist(array(
            'sTable' => "emp_attendance",
            'fields' => "min(`start_time`) as start_tm, max(end_time) as end_tm",
            'where' => "empid = $empid and start_dt = '$date'",
            'countOrResult' => 'row'
        ));

        return array(
            'in' => $result->start_tm,
            'out' => $result->end_tm,
            'intime' => strlen(trim($result->start_tm)) > 0 ? date("H:i:s", strtotime($result->start_tm)) : "00:00:00",
            'outtime' => strlen(trim($result->end_tm)) > 0 ? date("H:i:s", strtotime($result->end_tm)) : "00:00:00"
        );
    }

    public function getEmployeeAttendanceStatus($status, $empid, $date, $start_tm)
    {
        $this->load->model("attendancemodel");

        $new_status = "";
        if ($this->attendancemodel->isHalfDay($empid, $date, $start_tm)) {
            $new_status = "Half Day";
        }

        $rationalizationData = $this->attendancemodel->getRationalizationData($empid, $date);
        if (!empty($rationalizationData)) {
            if ($rationalizationData->status == "0") {
                $new_status = "Rationalization Pending";
            } else if ($rationalizationData->status == "1") {
                $new_status = "Rationalized";
            } else if ($rationalizationData->status == "2") {
                $new_status = "Rationalize In Progress";
            } else if ($rationalizationData->status == "3") {
                $new_status = "Rationalization Rejected";
            }
        }

        if (strlen(trim($new_status)) === 0) {
            switch ($status) {
                case "P":
                    $new_status = "Present";
                    break;
                case "A":
                case "A(H)":
                case "A(Wo)":
                case "A(LWP)":
                    $new_status = "Absent";
                    break;
                case "H":
                    $new_status = "Holiday";
                    break;
                case "Wo":
                    $new_status = "Weekly Off";
                    break;
                case "Tr":
                    $new_status = "Travel";
                    break;
                default:
                    $new_status = "Leave - " . $status;
                    break;
            }
        }

        return $new_status;
    }

    public function get_attendance_period($monYear)
    {
        $mYear = explode("-", $monYear);

        $start_period = $this->commonlib->get_config_variable("ATTENDANCE", "Attendance start period");
        $end_period = $this->commonlib->get_config_variable("ATTENDANCE", "Attendance end period");

        $sDay = substr($start_period, 0, strlen($start_period) - 2);
        $eDay = substr($end_period, 0, strlen($end_period) - 2);

        $month = $mYear[0];

        if ($end_period === "Last day of Month") {
            $eDay = date("t", strtotime($mYear[1] . "-" . $mYear[0] . "-01"));
        }

        if ((int)$sDay > (int)$eDay) {
            $month = $month - 1;
            $month = strlen($month) === 1 ? "0" . $month : $month;
        }

        $sDay = strlen($sDay) === 1 ? "0" . $sDay : $sDay;

        $start_date = $mYear[1] . "-" . $month . "-" . $sDay;

        if ($end_period === "Last day of Month") {
            $end_date = date("Y-m-t", strtotime($mYear[1] . "-" . $mYear[0] . "-01"));
        } else {
            $end_date = $mYear[1] . "-" . $mYear[0] . "-" . $eDay;
        }

        return array(
            'Start Date' => $start_date,
            'End Date' => $end_date
        );
    }

    public function get_non_half_days($emp_id, $cDate)
    {
        $is_nonweekly_off = 1;
        $result = array();

        if (intval($emp_id) > 0) {
            $emp_branch = $this->commonmodel->getTablelist(array(
                'sTable' => "emp",
                'fields' => "branch",
                'where' => "id = " . $emp_id,
                'countOrResult' => "row"
            ))->branch;

            $result = $this->commonmodel->getTablelist(array(
                'fields' => "wd.weekday, wdb.weekly_off_days",
                'sTable' => "week_days wd",
                'joinlist' => array(
                    array(
                        'table' => "week_days_branch wdb",
                        'condition' => "wdb.week_days_id = wd.id",
                        'type' => ""
                    )
                ),
                'where' => "wdb.half_day = 1 and branch_id = " . $emp_branch,
                'countOrResult' => "result",
            ));
        }

        if (count($result) === 0) {
            $result = $this->commonmodel->getTablelist(array(
                'fields' => "wd.weekday, wdb.weekly_off_days",
                'sTable' => "week_days wd",
                'joinlist' => array(
                    array(
                        'table' => "week_days_branch wdb",
                        'condition' => "wdb.week_days_id = wd.id",
                        'type' => ""
                    )
                ),
                'where' => "wdb.half_day = 1 and (branch_id is null or branch_id = 0)",
                'countOrResult' => "result",
            ));
        }

        $dw = date('l', strtotime($cDate));
        $status = $this->commonlib->get_config_variable('ATTENDANCE', 'Consider non-half days as');

        foreach ($result as $row) {
            $days = explode(",", $row->weekly_off_days);

            if ($dw === $row->weekday) {
                foreach ($days as $day) {
                    $month_year = date('M Y', strtotime($cDate)) . " " . $day . " " . $row->weekday;
                    $weeklyoff_date = date('Y-m-d', strtotime($month_year));

                    if (strtotime($weeklyoff_date) === strtotime($cDate)) {
                        $is_nonweekly_off = 0;
                    }
                }
            } else {
                $is_nonweekly_off = 0;
            }
        }

        if ($status !== "Weekly Off" || intval($is_nonweekly_off) !== 1) {
            $is_nonweekly_off = 0;
        }

        return $is_nonweekly_off;
    }

    public function is_half_day_weekly_off_applies($emp_id)
    {
        $status = 0;
        $chk_count = 0;

        if (intval($emp_id) > 0) {
            $emp_branch = $this->commonmodel->getTablelist(array(
                'sTable' => "emp",
                'fields' => "branch",
                'where' => "id = " . $emp_id,
                'countOrResult' => "row"
            ))->branch;

            $chk_count = $this->commonmodel->getTablelist(array(
                'fields' => "wd.weekday, wdb.weekly_off_days",
                'sTable' => "week_days wd",
                'joinlist' => array(
                    array(
                        'table' => "week_days_branch wdb",
                        'condition' => "wdb.week_days_id = wd.id",
                        'type' => ""
                    )
                ),
                'where' => "wdb.half_day = 1 and branch_id = " . $emp_branch,
                'countOrResult' => "count",
            ));
        }

        if (intval($chk_count) === 0) {
            $chk_count = $this->commonmodel->getTablelist(array(
                'fields' => "wd.weekday, wdb.weekly_off_days",
                'sTable' => "week_days wd",
                'joinlist' => array(
                    array(
                        'table' => "week_days_branch wdb",
                        'condition' => "wdb.week_days_id = wd.id",
                        'type' => ""
                    )
                ),
                'where' => "wdb.half_day = 1 and (branch_id is null or branch_id = 0)",
                'countOrResult' => "count",
            ));
        }

        $status = intval($chk_count) > 0 ? 1 : 0;

        return $status;
    }

    function get_shift_for_emp($emp_id, $att_date)
    {
        $actual_shift_in_time = "00:00";
        $shift_in_time = "00:00";
        $shift_half_time = "00:00";

        //Get the shift assign to an employee
        $emp_shift_details = $this->commonmodel->getTablelist(array(
            'sTable' => "shift_assign_detail sad",
            'fields' => "sm.start_time, sm.end_time, sm.cut_off_time, sm.half_time",
            'joinlist' => array(
                array(
                    'table' => "shift_assign sa",
                    'condition' => "sad.shift_assign_id = sa.id",
                    'type' => ""
                ),
                array(
                    'table' => "shift_master sm",
                    'condition' => "sa.shift_id = sm.id",
                    'type' => ""
                )
            ),
            'where' => "((sad.empid = $emp_id and str_to_date('" . $att_date . "', '%Y-%m-%d') >= sa.start_date and str_to_date('" . $att_date . "', '%Y-%m-%d') <= sa.end_date)) or
                ((sad.empid = $emp_id and str_to_date('" . $att_date . "', '%Y-%m-%d') >= sa.start_date and (sa.end_date is null OR sa.end_date =  '0000-00-00')))",
            'countOrResult' => "row"
        ));

        if (count($emp_shift_details) > 0) {
            $actual_shift_in_time = $emp_shift_details->start_time;
            $new_in_time = strtotime("+" . $emp_shift_details->cut_off_time . " minutes", strtotime($emp_shift_details->start_time));
            $shift_in_time = date('H:i:s', $new_in_time);
            $shift_half_time = $emp_shift_details->half_time;
        } else {
            //Get default shift in case no shift is assigned to the employee
            $default_shift = $this->commonmodel->getTablelist(array(
                'sTable' => "shift_master",
                'fields' => "start_time, end_time, cut_off_time, half_time",
                'where' => "is_default = 1",
                'countOrResult' => "row"
            ));

            if (isset($default_shift) && count($default_shift) > 0) {
                $actual_shift_in_time = $default_shift->start_time;
                $new_in_time = strtotime("+" . $default_shift->cut_off_time . " minutes", strtotime($default_shift->start_time));
                $shift_in_time = date('H:i:s', $new_in_time);
                $shift_half_time = $default_shift->half_time;
            }
        }

        return array(
            'shift_in_time' => $shift_in_time,
            'shift_half_time' => $shift_half_time,
            'actual_shift_in_time' => $actual_shift_in_time
        );
    }

    public function get_emp_leave_balance($emp_id)
    {
        $leave_balance = "";

        $emp_leaves = $this->getTablelist(array(
            'sTable' => "emp_leaves as el",
            'fields' => "lt.leavetype, el.balance",
            'joinlist' => array(
                array(
                    'table' => "leave_type as lt",
                    'condition' => "lt.id = el.type",
                    'type' => ""
                )
            ),
            'where' => "lt.is_lwp = 'No' and lt.status = 'Active' and el.empid = " . $emp_id
        ));

        foreach ($emp_leaves as $row) {
            $leave_names = explode(" ", $row->leavetype);
            $short_name = "";

            for ($i = 0; $i < count($leave_names); $i++) {
                $short_name = $short_name . substr($leave_names[$i], 0, 1);
            }

            $leave_balance = $leave_balance . $short_name . ": " . $row->balance . " | ";
        }

        return rtrim($leave_balance, " | ");
    }

    public function getLoginAttemptTime($empEmail, $date)
    {

        $logintime = $this->getTablelist(array(
            'sTable' => "login_attempts la",
            'fields' => "la.time",
            'where' => "la.login =" . $this->db->escape($empEmail)

        ));

        foreach ($logintime as $time) {
            if ($date === date("Y-m-d", $time->time)) {
                return $time->time;
            }
        }
    }
}