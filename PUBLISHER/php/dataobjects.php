<?php

/**
 * Data objects generated from PUBLISHER/docs/db.sql.
 * Keep table/column definitions in sync with the database schema.
 */

abstract class publisher_data_object
{
    protected $_myconn;
    protected $_rs = false;
    protected $_data = [];
    protected $_is_new = true;

    protected static $_table = '';
    protected static $_columns = [];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function __construct($myconn, $_id = 0, $my_rows = NULL, $_ssql = '') {
        $this->_myconn = $myconn;
        $this->initData();

        if (count(static::$_primaryKey) === 1) {
            $this->_data[static::$_primaryKey[0]] = $_id;
        } elseif (is_array($_id)) {
            foreach (static::$_primaryKey as $key) {
                if (array_key_exists($key, $_id)) {
                    $this->_data[$key] = $_id[$key];
                }
            }
        }

        $all_rows = false;
        if ($_ssql !== '') {
            $all_rows = $this->_myconn->getRS($_ssql);
        } elseif ($my_rows !== NULL) {
            $all_rows = $this->filterRowsByPrimaryKey($my_rows);
        } elseif ($this->hasPrimaryKeyValue()) {
            [$where, $params] = $this->primaryKeyWhere();
            $all_rows = $this->_myconn->getRS('SELECT * FROM `' . static::$_table . '` WHERE ' . $where . ' LIMIT 1', $params);
        }

        if ($all_rows) {
            $this->loadRow($all_rows[0]);
            $this->_rs = $all_rows;
            $this->_is_new = false;
        }
    }

    protected function initData() {
        foreach (static::$_columns as $column) {
            $this->_data[$column] = null;
        }
    }

    protected function loadRow($row) {
        foreach (static::$_columns as $column) {
            $this->_data[$column] = array_key_exists($column, $row) ? $row[$column] : null;
        }
    }

    protected function filterRowsByPrimaryKey($rows) {
        $result = [];
        foreach ($rows as $row) {
            $match = true;
            foreach (static::$_primaryKey as $key) {
                if (!array_key_exists($key, $row) || (string)$row[$key] !== (string)$this->_data[$key]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $result[] = $row;
            }
        }
        return $result ?: false;
    }

    protected function hasPrimaryKeyValue() {
        foreach (static::$_primaryKey as $key) {
            if (!isset($this->_data[$key]) || $this->_data[$key] === '' || $this->_data[$key] === 0 || $this->_data[$key] === '0') {
                return false;
            }
        }
        return true;
    }

    protected function primaryKeyWhere() {
        $parts = [];
        $params = [];
        foreach (static::$_primaryKey as $key) {
            $parts[] = '`' . $key . '` = ?';
            $params[] = $this->_data[$key];
        }
        return [implode(' AND ', $parts), $params];
    }

    public function get_id() {
        if (count(static::$_primaryKey) === 1) {
            return $this->_data[static::$_primaryKey[0]];
        }

        $id = [];
        foreach (static::$_primaryKey as $key) {
            $id[$key] = $this->_data[$key];
        }
        return $id;
    }

    public function set_id($val) {
        if (count(static::$_primaryKey) === 1) {
            $this->_data[static::$_primaryKey[0]] = $val;
            return;
        }

        if (is_array($val)) {
            foreach (static::$_primaryKey as $key) {
                if (array_key_exists($key, $val)) {
                    $this->_data[$key] = $val[$key];
                }
            }
        }
    }

    public function get_rs() {
        return $this->_rs;
    }

    public function tableName() {
        return static::$_table;
    }

    public function columns() {
        return static::$_columns;
    }

    public function toArray() {
        return $this->_data;
    }

    public function get($field) {
        return array_key_exists($field, $this->_data) ? $this->_data[$field] : null;
    }

    public function set($field, $value) {
        if (array_key_exists($field, $this->_data)) {
            $this->_data[$field] = $value;
        }
    }

    public function __call($name, $arguments) {
        if (array_key_exists($name, $this->_data)) {
            if (count($arguments) === 0) {
                return $this->_data[$name];
            }
            $this->_data[$name] = $arguments[0];
            return null;
        }

        throw new BadMethodCallException('Unknown field or method ' . static::$_table . '::' . $name . '()');
    }

    public function Savedata() {
        if ($this->_is_new || !$this->hasPrimaryKeyValue()) {
            return $this->insertRow();
        }

        return $this->updateRow();
    }

    protected function insertRow() {
        $columns = [];
        $params = [];
        foreach (static::$_columns as $column) {
            if (static::$_autoIncrement && count(static::$_primaryKey) === 1 && $column === static::$_primaryKey[0]) {
                continue;
            }
            $columns[] = $column;
            $params[] = $this->_data[$column];
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $quotedColumns = '`' . implode('`, `', $columns) . '`';
        $sql = 'INSERT INTO `' . static::$_table . '` (' . $quotedColumns . ') VALUES (' . $placeholders . ')';
        $result = $this->_myconn->execSQL($sql, $params);

        if ($result === false) {
            return false;
        }

        if (static::$_autoIncrement && count(static::$_primaryKey) === 1) {
            $this->_data[static::$_primaryKey[0]] = $result;
        }
        $this->_is_new = false;
        return true;
    }

    protected function updateRow() {
        $set = [];
        $params = [];
        foreach (static::$_columns as $column) {
            if (in_array($column, static::$_primaryKey, true)) {
                continue;
            }
            $set[] = '`' . $column . '` = ?';
            $params[] = $this->_data[$column];
        }

        if (!$set) {
            return true;
        }

        [$where, $whereParams] = $this->primaryKeyWhere();
        $params = array_merge($params, $whereParams);
        $sql = 'UPDATE `' . static::$_table . '` SET ' . implode(', ', $set) . ' WHERE ' . $where;
        $result = $this->_myconn->execSQL($sql, $params);

        return $result !== false;
    }

    public function Delete() {
        if (!$this->hasPrimaryKeyValue()) {
            return false;
        }

        [$where, $params] = $this->primaryKeyWhere();
        $sql = 'DELETE FROM `' . static::$_table . '` WHERE ' . $where;
        $result = $this->_myconn->execSQL($sql, $params);

        return $result !== false;
    }
}

/*
FIELDS
id
name
company_name
status
created_at
updated_at
*/
class accounts extends publisher_data_object
{
    protected static $_table = 'accounts';
    protected static $_columns = ['id', 'name', 'company_name', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function company_name($val = NULL) {
        if ($val === NULL) { return $this->get('company_name'); }
        $this->set('company_name', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
user_id
role
status
created_at
updated_at
*/
class account_users extends publisher_data_object
{
    protected static $_table = 'account_users';
    protected static $_columns = ['id', 'account_id', 'user_id', 'role', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function user_id($val = NULL) {
        if ($val === NULL) { return $this->get('user_id'); }
        $this->set('user_id', $val);
    }

    public function role($val = NULL) {
        if ($val === NULL) { return $this->get('role'); }
        $this->set('role', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
content_idea_id
action_type
provider
model
prompt
response
tokens_input
tokens_output
cost_estimate
status
error_message
created_by
created_at
*/
class ai_generation_logs extends publisher_data_object
{
    protected static $_table = 'ai_generation_logs';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'content_idea_id', 'action_type', 'provider', 'model', 'prompt', 'response', 'tokens_input', 'tokens_output', 'cost_estimate', 'status', 'error_message', 'created_by', 'created_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function content_idea_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_idea_id'); }
        $this->set('content_idea_id', $val);
    }

    public function action_type($val = NULL) {
        if ($val === NULL) { return $this->get('action_type'); }
        $this->set('action_type', $val);
    }

    public function provider($val = NULL) {
        if ($val === NULL) { return $this->get('provider'); }
        $this->set('provider', $val);
    }

    public function model($val = NULL) {
        if ($val === NULL) { return $this->get('model'); }
        $this->set('model', $val);
    }

    public function prompt($val = NULL) {
        if ($val === NULL) { return $this->get('prompt'); }
        $this->set('prompt', $val);
    }

    public function response($val = NULL) {
        if ($val === NULL) { return $this->get('response'); }
        $this->set('response', $val);
    }

    public function tokens_input($val = NULL) {
        if ($val === NULL) { return $this->get('tokens_input'); }
        $this->set('tokens_input', $val);
    }

    public function tokens_output($val = NULL) {
        if ($val === NULL) { return $this->get('tokens_output'); }
        $this->set('tokens_output', $val);
    }

    public function cost_estimate($val = NULL) {
        if ($val === NULL) { return $this->get('cost_estimate'); }
        $this->set('cost_estimate', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function error_message($val = NULL) {
        if ($val === NULL) { return $this->get('error_message'); }
        $this->set('error_message', $val);
    }

    public function created_by($val = NULL) {
        if ($val === NULL) { return $this->get('created_by'); }
        $this->set('created_by', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
name
provider
model
temperature
max_tokens
system_prompt
default_writing_style_id
default_template_id
default_language
created_at
updated_at
*/
class ai_profiles extends publisher_data_object
{
    protected static $_table = 'ai_profiles';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'provider', 'model', 'temperature', 'max_tokens', 'system_prompt', 'default_writing_style_id', 'default_template_id', 'default_language', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function provider($val = NULL) {
        if ($val === NULL) { return $this->get('provider'); }
        $this->set('provider', $val);
    }

    public function model($val = NULL) {
        if ($val === NULL) { return $this->get('model'); }
        $this->set('model', $val);
    }

    public function temperature($val = NULL) {
        if ($val === NULL) { return $this->get('temperature'); }
        $this->set('temperature', $val);
    }

    public function max_tokens($val = NULL) {
        if ($val === NULL) { return $this->get('max_tokens'); }
        $this->set('max_tokens', $val);
    }

    public function system_prompt($val = NULL) {
        if ($val === NULL) { return $this->get('system_prompt'); }
        $this->set('system_prompt', $val);
    }

    public function default_writing_style_id($val = NULL) {
        if ($val === NULL) { return $this->get('default_writing_style_id'); }
        $this->set('default_writing_style_id', $val);
    }

    public function default_template_id($val = NULL) {
        if ($val === NULL) { return $this->get('default_template_id'); }
        $this->set('default_template_id', $val);
    }

    public function default_language($val = NULL) {
        if ($val === NULL) { return $this->get('default_language'); }
        $this->set('default_language', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
parent_id
name
slug
description
status
created_at
updated_at
*/
class content_categories extends publisher_data_object
{
    protected static $_table = 'content_categories';
    protected static $_columns = ['id', 'account_id', 'property_id', 'parent_id', 'name', 'slug', 'description', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function parent_id($val = NULL) {
        if ($val === NULL) { return $this->get('parent_id'); }
        $this->set('parent_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function slug($val = NULL) {
        if ($val === NULL) { return $this->get('slug'); }
        $this->set('slug', $val);
    }

    public function description($val = NULL) {
        if ($val === NULL) { return $this->get('description'); }
        $this->set('description', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
content_idea_id
embedding
embedding_model
source_text_hash
created_at
*/
class content_embeddings extends publisher_data_object
{
    protected static $_table = 'content_embeddings';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'content_idea_id', 'embedding', 'embedding_model', 'source_text_hash', 'created_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function content_idea_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_idea_id'); }
        $this->set('content_idea_id', $val);
    }

    public function embedding($val = NULL) {
        if ($val === NULL) { return $this->get('embedding'); }
        $this->set('embedding', $val);
    }

    public function embedding_model($val = NULL) {
        if ($val === NULL) { return $this->get('embedding_model'); }
        $this->set('embedding_model', $val);
    }

    public function source_text_hash($val = NULL) {
        if ($val === NULL) { return $this->get('source_text_hash'); }
        $this->set('source_text_hash', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_type_id
category_id
title
summary
tags
sections
tone
language
instructions
image_prompt
prompt
ai_response_json
similarity_score
status
created_by
content_item_id
created_at
updated_at
*/
class content_ideas extends publisher_data_object
{
    protected static $_table = 'content_ideas';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_type_id', 'category_id', 'title', 'summary', 'tags', 'sections', 'tone', 'language', 'instructions', 'image_prompt', 'prompt', 'ai_response_json', 'similarity_score', 'status', 'created_by', 'content_item_id', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_type_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_type_id'); }
        $this->set('content_type_id', $val);
    }

    public function category_id($val = NULL) {
        if ($val === NULL) { return $this->get('category_id'); }
        $this->set('category_id', $val);
    }

    public function title($val = NULL) {
        if ($val === NULL) { return $this->get('title'); }
        $this->set('title', $val);
    }

    public function summary($val = NULL) {
        if ($val === NULL) { return $this->get('summary'); }
        $this->set('summary', $val);
    }

    public function tags($val = NULL) {
        if ($val === NULL) { return $this->get('tags'); }
        $this->set('tags', $val);
    }

    public function sections($val = NULL) {
        if ($val === NULL) { return $this->get('sections'); }
        $this->set('sections', $val);
    }

    public function tone($val = NULL) {
        if ($val === NULL) { return $this->get('tone'); }
        $this->set('tone', $val);
    }

    public function language($val = NULL) {
        if ($val === NULL) { return $this->get('language'); }
        $this->set('language', $val);
    }

    public function instructions($val = NULL) {
        if ($val === NULL) { return $this->get('instructions'); }
        $this->set('instructions', $val);
    }

    public function image_prompt($val = NULL) {
        if ($val === NULL) { return $this->get('image_prompt'); }
        $this->set('image_prompt', $val);
    }

    public function prompt($val = NULL) {
        if ($val === NULL) { return $this->get('prompt'); }
        $this->set('prompt', $val);
    }

    public function ai_response_json($val = NULL) {
        if ($val === NULL) { return $this->get('ai_response_json'); }
        $this->set('ai_response_json', $val);
    }

    public function similarity_score($val = NULL) {
        if ($val === NULL) { return $this->get('similarity_score'); }
        $this->set('similarity_score', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_by($val = NULL) {
        if ($val === NULL) { return $this->get('created_by'); }
        $this->set('created_by', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_type_id
source_idea_id
title
slug
summary
body
status
language
writing_style_id
template_id
ai_profile_id
created_by
approved_by
published_at
created_at
updated_at
*/
class content_items extends publisher_data_object
{
    protected static $_table = 'content_items';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_type_id', 'source_idea_id', 'title', 'slug', 'summary', 'body', 'status', 'language', 'writing_style_id', 'template_id', 'ai_profile_id', 'created_by', 'approved_by', 'published_at', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_type_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_type_id'); }
        $this->set('content_type_id', $val);
    }

    public function source_idea_id($val = NULL) {
        if ($val === NULL) { return $this->get('source_idea_id'); }
        $this->set('source_idea_id', $val);
    }

    public function title($val = NULL) {
        if ($val === NULL) { return $this->get('title'); }
        $this->set('title', $val);
    }

    public function slug($val = NULL) {
        if ($val === NULL) { return $this->get('slug'); }
        $this->set('slug', $val);
    }

    public function summary($val = NULL) {
        if ($val === NULL) { return $this->get('summary'); }
        $this->set('summary', $val);
    }

    public function body($val = NULL) {
        if ($val === NULL) { return $this->get('body'); }
        $this->set('body', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function language($val = NULL) {
        if ($val === NULL) { return $this->get('language'); }
        $this->set('language', $val);
    }

    public function writing_style_id($val = NULL) {
        if ($val === NULL) { return $this->get('writing_style_id'); }
        $this->set('writing_style_id', $val);
    }

    public function template_id($val = NULL) {
        if ($val === NULL) { return $this->get('template_id'); }
        $this->set('template_id', $val);
    }

    public function ai_profile_id($val = NULL) {
        if ($val === NULL) { return $this->get('ai_profile_id'); }
        $this->set('ai_profile_id', $val);
    }

    public function created_by($val = NULL) {
        if ($val === NULL) { return $this->get('created_by'); }
        $this->set('created_by', $val);
    }

    public function approved_by($val = NULL) {
        if ($val === NULL) { return $this->get('approved_by'); }
        $this->set('approved_by', $val);
    }

    public function published_at($val = NULL) {
        if ($val === NULL) { return $this->get('published_at'); }
        $this->set('published_at', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
content_item_id
category_id
*/
class content_item_categories extends publisher_data_object
{
    protected static $_table = 'content_item_categories';
    protected static $_columns = ['content_item_id', 'category_id'];
    protected static $_primaryKey = ['content_item_id', 'category_id'];
    protected static $_autoIncrement = false;

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function category_id($val = NULL) {
        if ($val === NULL) { return $this->get('category_id'); }
        $this->set('category_id', $val);
    }

}

/*
FIELDS
content_item_id
tag_id
source
*/
class content_item_tags extends publisher_data_object
{
    protected static $_table = 'content_item_tags';
    protected static $_columns = ['content_item_id', 'tag_id', 'source'];
    protected static $_primaryKey = ['content_item_id', 'tag_id'];
    protected static $_autoIncrement = false;

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function tag_id($val = NULL) {
        if ($val === NULL) { return $this->get('tag_id'); }
        $this->set('tag_id', $val);
    }

    public function source($val = NULL) {
        if ($val === NULL) { return $this->get('source'); }
        $this->set('source', $val);
    }

}

/*
FIELDS
id
account_id
property_id
name
description
content_type_id
category_id
template_id
writing_style_id
ai_profile_id
frequency
auto_generate
auto_publish
status
created_at
updated_at
*/
class content_plans extends publisher_data_object
{
    protected static $_table = 'content_plans';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'description', 'content_type_id', 'category_id', 'template_id', 'writing_style_id', 'ai_profile_id', 'frequency', 'auto_generate', 'auto_publish', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function description($val = NULL) {
        if ($val === NULL) { return $this->get('description'); }
        $this->set('description', $val);
    }

    public function content_type_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_type_id'); }
        $this->set('content_type_id', $val);
    }

    public function category_id($val = NULL) {
        if ($val === NULL) { return $this->get('category_id'); }
        $this->set('category_id', $val);
    }

    public function template_id($val = NULL) {
        if ($val === NULL) { return $this->get('template_id'); }
        $this->set('template_id', $val);
    }

    public function writing_style_id($val = NULL) {
        if ($val === NULL) { return $this->get('writing_style_id'); }
        $this->set('writing_style_id', $val);
    }

    public function ai_profile_id($val = NULL) {
        if ($val === NULL) { return $this->get('ai_profile_id'); }
        $this->set('ai_profile_id', $val);
    }

    public function frequency($val = NULL) {
        if ($val === NULL) { return $this->get('frequency'); }
        $this->set('frequency', $val);
    }

    public function auto_generate($val = NULL) {
        if ($val === NULL) { return $this->get('auto_generate'); }
        $this->set('auto_generate', $val);
    }

    public function auto_publish($val = NULL) {
        if ($val === NULL) { return $this->get('auto_publish'); }
        $this->set('auto_publish', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_plan_id
content_idea_id
content_item_id
status
run_at
error_message
created_at
updated_at
*/
class content_plan_runs extends publisher_data_object
{
    protected static $_table = 'content_plan_runs';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_plan_id', 'content_idea_id', 'content_item_id', 'status', 'run_at', 'error_message', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_plan_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_plan_id'); }
        $this->set('content_plan_id', $val);
    }

    public function content_idea_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_idea_id'); }
        $this->set('content_idea_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function run_at($val = NULL) {
        if ($val === NULL) { return $this->get('run_at'); }
        $this->set('run_at', $val);
    }

    public function error_message($val = NULL) {
        if ($val === NULL) { return $this->get('error_message'); }
        $this->set('error_message', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
distribution_channel_id
external_id
external_url
status
scheduled_at
published_at
error_message
created_at
updated_at
*/
class content_publications extends publisher_data_object
{
    protected static $_table = 'content_publications';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'distribution_channel_id', 'external_id', 'external_url', 'status', 'scheduled_at', 'published_at', 'error_message', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function distribution_channel_id($val = NULL) {
        if ($val === NULL) { return $this->get('distribution_channel_id'); }
        $this->set('distribution_channel_id', $val);
    }

    public function external_id($val = NULL) {
        if ($val === NULL) { return $this->get('external_id'); }
        $this->set('external_id', $val);
    }

    public function external_url($val = NULL) {
        if ($val === NULL) { return $this->get('external_url'); }
        $this->set('external_url', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function scheduled_at($val = NULL) {
        if ($val === NULL) { return $this->get('scheduled_at'); }
        $this->set('scheduled_at', $val);
    }

    public function published_at($val = NULL) {
        if ($val === NULL) { return $this->get('published_at'); }
        $this->set('published_at', $val);
    }

    public function error_message($val = NULL) {
        if ($val === NULL) { return $this->get('error_message'); }
        $this->set('error_message', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
reviewed_by
status
comments
created_at
*/
class content_reviews extends publisher_data_object
{
    protected static $_table = 'content_reviews';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'reviewed_by', 'status', 'comments', 'created_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function reviewed_by($val = NULL) {
        if ($val === NULL) { return $this->get('reviewed_by'); }
        $this->set('reviewed_by', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function comments($val = NULL) {
        if ($val === NULL) { return $this->get('comments'); }
        $this->set('comments', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
content_idea_id
compared_content_item_id
similarity_score
reason
created_at
*/
class content_similarity_checks extends publisher_data_object
{
    protected static $_table = 'content_similarity_checks';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'content_idea_id', 'compared_content_item_id', 'similarity_score', 'reason', 'created_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function content_idea_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_idea_id'); }
        $this->set('content_idea_id', $val);
    }

    public function compared_content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('compared_content_item_id'); }
        $this->set('compared_content_item_id', $val);
    }

    public function similarity_score($val = NULL) {
        if ($val === NULL) { return $this->get('similarity_score'); }
        $this->set('similarity_score', $val);
    }

    public function reason($val = NULL) {
        if ($val === NULL) { return $this->get('reason'); }
        $this->set('reason', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_type_id
name
description
structure_json
is_default
status
created_at
updated_at
*/
class content_templates extends publisher_data_object
{
    protected static $_table = 'content_templates';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_type_id', 'name', 'description', 'structure_json', 'is_default', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_type_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_type_id'); }
        $this->set('content_type_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function description($val = NULL) {
        if ($val === NULL) { return $this->get('description'); }
        $this->set('description', $val);
    }

    public function structure_json($val = NULL) {
        if ($val === NULL) { return $this->get('structure_json'); }
        $this->set('structure_json', $val);
    }

    public function is_default($val = NULL) {
        if ($val === NULL) { return $this->get('is_default'); }
        $this->set('is_default', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
name
slug
description
default_word_count
status
created_at
updated_at
*/
class content_types extends publisher_data_object
{
    protected static $_table = 'content_types';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'slug', 'description', 'default_word_count', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function slug($val = NULL) {
        if ($val === NULL) { return $this->get('slug'); }
        $this->set('slug', $val);
    }

    public function description($val = NULL) {
        if ($val === NULL) { return $this->get('description'); }
        $this->set('description', $val);
    }

    public function default_word_count($val = NULL) {
        if ($val === NULL) { return $this->get('default_word_count'); }
        $this->set('default_word_count', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
name
type
credentials_json
settings_json
status
created_at
updated_at
*/
class distribution_channels extends publisher_data_object
{
    protected static $_table = 'distribution_channels';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'type', 'credentials_json', 'settings_json', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function type($val = NULL) {
        if ($val === NULL) { return $this->get('type'); }
        $this->set('type', $val);
    }

    public function credentials_json($val = NULL) {
        if ($val === NULL) { return $this->get('credentials_json'); }
        $this->set('credentials_json', $val);
    }

    public function settings_json($val = NULL) {
        if ($val === NULL) { return $this->get('settings_json'); }
        $this->set('settings_json', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
property_id
name
description
active
account_id
created_at
updated_at
*/
class image_styles extends publisher_data_object
{
    protected static $_table = 'image_styles';
    protected static $_columns = ['id', 'property_id', 'name', 'description', 'active', 'account_id', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function description($val = NULL) {
        if ($val === NULL) { return $this->get('description'); }
        $this->set('description', $val);
    }

    public function active($val = NULL) {
        if ($val === NULL) { return $this->get('active'); }
        $this->set('active', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
content_item_id
type
source
prompt
file_path
external_url
alt_text
caption
metadata_json
created_at
*/
class media_assets extends publisher_data_object
{
    protected static $_table = 'media_assets';
    protected static $_columns = ['id', 'account_id', 'property_id', 'content_item_id', 'type', 'source', 'prompt', 'file_path', 'external_url', 'alt_text', 'caption', 'metadata_json', 'created_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function content_item_id($val = NULL) {
        if ($val === NULL) { return $this->get('content_item_id'); }
        $this->set('content_item_id', $val);
    }

    public function type($val = NULL) {
        if ($val === NULL) { return $this->get('type'); }
        $this->set('type', $val);
    }

    public function source($val = NULL) {
        if ($val === NULL) { return $this->get('source'); }
        $this->set('source', $val);
    }

    public function prompt($val = NULL) {
        if ($val === NULL) { return $this->get('prompt'); }
        $this->set('prompt', $val);
    }

    public function file_path($val = NULL) {
        if ($val === NULL) { return $this->get('file_path'); }
        $this->set('file_path', $val);
    }

    public function external_url($val = NULL) {
        if ($val === NULL) { return $this->get('external_url'); }
        $this->set('external_url', $val);
    }

    public function alt_text($val = NULL) {
        if ($val === NULL) { return $this->get('alt_text'); }
        $this->set('alt_text', $val);
    }

    public function caption($val = NULL) {
        if ($val === NULL) { return $this->get('caption'); }
        $this->set('caption', $val);
    }

    public function metadata_json($val = NULL) {
        if ($val === NULL) { return $this->get('metadata_json'); }
        $this->set('metadata_json', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

}

/*
FIELDS
id
account_id
name
type
primary_url
default_language
timezone
settings_json
status
created_at
updated_at
*/
class properties extends publisher_data_object
{
    protected static $_table = 'properties';
    protected static $_columns = ['id', 'account_id', 'name', 'type', 'primary_url', 'default_language', 'timezone', 'settings_json', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function type($val = NULL) {
        if ($val === NULL) { return $this->get('type'); }
        $this->set('type', $val);
    }

    public function primary_url($val = NULL) {
        if ($val === NULL) { return $this->get('primary_url'); }
        $this->set('primary_url', $val);
    }

    public function default_language($val = NULL) {
        if ($val === NULL) { return $this->get('default_language'); }
        $this->set('default_language', $val);
    }

    public function timezone($val = NULL) {
        if ($val === NULL) { return $this->get('timezone'); }
        $this->set('timezone', $val);
    }

    public function settings_json($val = NULL) {
        if ($val === NULL) { return $this->get('settings_json'); }
        $this->set('settings_json', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
property_id
user_id
role
status
created_at
updated_at
*/
class property_users extends publisher_data_object
{
    protected static $_table = 'property_users';
    protected static $_columns = ['id', 'property_id', 'user_id', 'role', 'status', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function user_id($val = NULL) {
        if ($val === NULL) { return $this->get('user_id'); }
        $this->set('user_id', $val);
    }

    public function role($val = NULL) {
        if ($val === NULL) { return $this->get('role'); }
        $this->set('role', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
account_id
property_id
name
slug
source
created_at
updated_at
*/
class tags extends publisher_data_object
{
    protected static $_table = 'tags';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'slug', 'source', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function slug($val = NULL) {
        if ($val === NULL) { return $this->get('slug'); }
        $this->set('slug', $val);
    }

    public function source($val = NULL) {
        if ($val === NULL) { return $this->get('source'); }
        $this->set('source', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}

/*
FIELDS
id
name
email
password_hash
status
last_login_at
last_account_id
created_at
updated_at
*/
class users extends publisher_data_object
{
    protected static $_table = 'users';
    protected static $_columns = ['id', 'name', 'email', 'password_hash', 'status', 'last_login_at', 'last_account_id', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function email($val = NULL) {
        if ($val === NULL) { return $this->get('email'); }
        $this->set('email', $val);
    }

    public function password_hash($val = NULL) {
        if ($val === NULL) { return $this->get('password_hash'); }
        $this->set('password_hash', $val);
    }

    public function status($val = NULL) {
        if ($val === NULL) { return $this->get('status'); }
        $this->set('status', $val);
    }

    public function last_login_at($val = NULL) {
        if ($val === NULL) { return $this->get('last_login_at'); }
        $this->set('last_login_at', $val);
    }

    public function last_account_id($val = NULL) {
        if ($val === NULL) { return $this->get('last_account_id'); }
        $this->set('last_account_id', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

    public function firstName($val = NULL) {
        if ($val === NULL) {
            $parts = preg_split('/\s+/', trim((string)$this->name()), 2);
            return $parts[0] ?? '';
        }
        $lastName = $this->lastName();
        $this->name(trim($val . ' ' . $lastName));
    }

    public function lastName($val = NULL) {
        if ($val === NULL) {
            $parts = preg_split('/\s+/', trim((string)$this->name()), 2);
            return $parts[1] ?? '';
        }
        $firstName = $this->firstName();
        $this->name(trim($firstName . ' ' . $val));
    }

    public function password($val = NULL) {
        if ($val === NULL) { return ''; }
        if ($val !== '') {
            $this->password_hash(password_hash($val, PASSWORD_DEFAULT));
        }
    }

    public function hash($val = NULL) {
        if ($val === NULL) { return $this->password_hash(); }
        $this->password_hash($val);
    }

    public function userProfile($val = NULL) { return 1; }

    public function active($val = NULL) {
        if ($val === NULL) { return $this->status() === 'active' ? 1 : 0; }
        $this->status($val ? 'active' : 'inactive');
    }

    public function bodybgcolor($val = NULL) { return ''; }
    public function bodytextcolor($val = NULL) { return ''; }
    public function headerbgcolor($val = NULL) { return ''; }
    public function headertextcolor($val = NULL) { return ''; }
    public function homebgimage($val = NULL) { return ''; }
    public function css($val = NULL) { return ''; }
    public function home_html($val = NULL) { return ''; }
    public function block1_html($val = NULL) { return ''; }
    public function block2_html($val = NULL) { return ''; }
    public function block3_html($val = NULL) { return ''; }
    public function language($val = NULL) { return 1; }

    public function Savedata() {
        $now = date('Y-m-d H:i:s');
        if (!$this->status()) {
            $this->status('active');
        }
        if (!$this->created_at()) {
            $this->created_at($now);
        }
        $this->updated_at($now);

        return parent::Savedata();
    }
}

/*
FIELDS
id
account_id
property_id
name
tone
language
instructions
created_at
updated_at
*/
class writing_styles extends publisher_data_object
{
    protected static $_table = 'writing_styles';
    protected static $_columns = ['id', 'account_id', 'property_id', 'name', 'tone', 'language', 'instructions', 'created_at', 'updated_at'];
    protected static $_primaryKey = ['id'];
    protected static $_autoIncrement = true;

    public function id($val = NULL) {
        if ($val === NULL) { return $this->get('id'); }
        $this->set('id', $val);
    }

    public function account_id($val = NULL) {
        if ($val === NULL) { return $this->get('account_id'); }
        $this->set('account_id', $val);
    }

    public function property_id($val = NULL) {
        if ($val === NULL) { return $this->get('property_id'); }
        $this->set('property_id', $val);
    }

    public function name($val = NULL) {
        if ($val === NULL) { return $this->get('name'); }
        $this->set('name', $val);
    }

    public function tone($val = NULL) {
        if ($val === NULL) { return $this->get('tone'); }
        $this->set('tone', $val);
    }

    public function language($val = NULL) {
        if ($val === NULL) { return $this->get('language'); }
        $this->set('language', $val);
    }

    public function instructions($val = NULL) {
        if ($val === NULL) { return $this->get('instructions'); }
        $this->set('instructions', $val);
    }

    public function created_at($val = NULL) {
        if ($val === NULL) { return $this->get('created_at'); }
        $this->set('created_at', $val);
    }

    public function updated_at($val = NULL) {
        if ($val === NULL) { return $this->get('updated_at'); }
        $this->set('updated_at', $val);
    }

}
