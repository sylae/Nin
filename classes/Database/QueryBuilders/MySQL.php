<?php

namespace Nin\Database\QueryBuilders;

use Nin\Database\QueryBuilder;

class MySQL extends QueryBuilder
{
	private function encode($o)
	{
		if(is_string($o)) {
			return "'" . $this->context->real_escape_string($o) . "'";
		} elseif(is_float($o)) {
			return str_replace(',', '.', strval(floatval($o)));
		} elseif(is_numeric($o)) {
			return intval($o);
		} elseif(is_null($o)) {
			return "null";
		} elseif (is_bool($o)) {
			return $o ? "1": "0";
		}

		return $o;
	}

	private function buildWhere()
	{
		if(count($this->where) == 0) {
			return '';
		}

		$ret = ' WHERE';

		for($i = 0; $i < count($this->where); $i++) {
			$where = $this->where[$i];

			$key = $where[0];
			$value = $where[1];
			$oper = $where[2];
			$logical = $where[3];

			if($key == '' || $oper == '') {
				continue;
			}

			if($i > 0) {
				$ret .= ' ' . $logical;
			}

			if(is_array($value)) {
				$items = '';
				for($j = 0; $j < count($value); $j++) {
					if ($j > 0) {
						$items .= ',';
					}
					$items .= $this->encode($value[$j]);
				}
				$ret .= ' `' . $key . '` IN (' . $items . ')';
			} else {
				$ret .= ' `' . $key . '`' . $oper . $this->encode($value);
			}
		}

		return $ret;
	}

	private function buildSelect()
	{
		$query = 'SELECT ';
		if(count($this->get) == 0) {
			$query .= '*';
		} else {
			$query .= implode(',', $this->get);
		}
		$query .= ' FROM ' . $this->table;
		$query .= $this->buildWhere();
		if($this->group != '') {
			$query .= ' GROUP BY `' . $this->group . '`';
		}
		for($i = 0; $i < count($this->orderby); $i++) {
			if($i == 0) {
				$query .= ' ORDER BY ';
			} else {
				$query .= ',';
			}
			$order = $this->orderby[$i];
			$query .= '`' . $order[0] . '`';
			if(strcasecmp($order[1], 'DESC') == 0) {
				$query .= ' DESC';
			}
		}
		if($this->limit[0] >= 0 && $this->limit[1] >= 0) {
			$num = $this->limit[1] - $this->limit[0];
			$query .= ' LIMIT ' . $this->limit[0] . ',' . $num;
		}
		return $query . ';';
	}

	private function buildUpdate()
	{
		if(count($this->set) == 0) {
			return '';
		}
		$query = 'UPDATE ' . $this->table . ' SET';
		$count = 0;
		for($i = 0; $i < count($this->set); $i++) {
			$set = $this->set[$i];
			if($count > 0) {
				$query .= ',';
			}
			$query .= ' `' . $set[0] . '`=' . $this->encode($set[1]);
			$count++;
		}
		$query .= $this->buildWhere();
		return $query . ';';
	}

	private function buildInsert()
	{
		$numRows = count($this->insertValues);
		if($numRows == 0) {
			return '';
		}
		$query = 'INSERT INTO ' . $this->table . ' (';
		$numCols = 0;
		for($i = 0; $i < $numRows; $i++) {
			$row = $this->insertValues[$i];
			if($numCols == 0 && $i == 0) {
				$keys = array_keys($row);
				$numCols = count($keys);
				for($j = 0; $j < $numCols; $j++) {
					if($j > 0) {
						$query .= ',';
					}
					$query .= $keys[$j];
				}
				$query .= ') VALUES ';
			}
			if($i > 0) {
				$query .= ',';
			}
			$query .= '(';
			$vals = array_values($row);
			for($j = 0; $j < $numCols; $j++) {
				if($j > 0) {
					$query .= ',';
				}
				$query .= $this->encode($vals[$j]);
			}
			$query .= ')';
		}
		return $query . ';';
	}

	private function buildDelete()
	{
		$query = 'DELETE FROM ' . $this->table;
		$query .= $this->buildWhere();
		return $query . ';';
	}

	private function buildCount()
	{
		$query = 'SELECT COUNT(*) AS c FROM ' . $this->table;
		$query .= $this->buildWhere();
		return $query . ';';
	}

	public function build()
	{
		if($this->method == '') {
			return '';
		}

		if($this->method == 'SELECT') {
			return $this->buildSelect();
		} elseif($this->method == 'UPDATE') {
			return $this->buildUpdate();
		} elseif($this->method == 'INSERT') {
			return $this->buildInsert();
		} elseif($this->method == 'DELETE') {
			return $this->buildDelete();
		} elseif($this->method == 'COUNT') {
			return $this->buildCount();
		}

		return '';
	}
}
