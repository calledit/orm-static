<?php

class QUERYview {
	public $sql_query;
	public $filtered_sql_query;
	public $limited_sql_query;

	//a uniq id that is given to thw view based on the query
	public $viewIdentifier;
	//how many rows to show
	public $limit = 200;

	//the spliter that is used in the csv files
	public $csv_split = ',';

	//Maps colun numers to column names and the reverse
	public $col2num = array();
	public $num2col = array();

	//The columns we will use to order and the direction to order them by
	public $orders = array();

	//If the selection is to be inverted
	public $invert = array();

	//The columns we will filter in and the value we want to filter
	public $filters = array();
	//are we trying to create an export file
	public $export = false;
	//The url path where we can view the table
	public $path = '';
	//information about the column in the databse
	public $column_metas = array();
	//number of rows the query returns when we have no filters taken from the last non filterd execution
	public $estimated_num_rows = 0;
	//The name that each column gets in the GUI
	public $visible_column_names = array();
	//The columns from the query that we show the user
	public $visible_columns = array();

	//The rows that the query returned
	public $rows = array();

	//functions to change the value of a column in the table (to make a value clickable with the sue of a <a>)
	public $html_col_map = array();

	//functions to chnage the value of a column in the table
	public $col_map = array();
	//functions to chnage the value of a column in the select boxes
	public $col_map_distinct = array();
	//functions or values that map to other values in the database
	public $col_sql_map = array();

	//prints a csv file with the featched files
	public function csv($Split = ','){
		$outstream = fopen("php://temp", 'r+');

		//get and write column names
		$column_names = array();
		foreach($this->visible_columns AS $column_name){
			$ColVisibleName = $column_name;
			if(isset($this->col2num[$column_name])){
				if(isset($this->visible_column_names[$column_name])){
					$ColVisibleName = $this->visible_column_names[$column_name];
				}
				$column_names[] = $ColVisibleName;
			}
		}
		fputcsv($outstream, $column_names, $Split, '"');
		foreach($this->rows as $dataRow){
			$visRow = array();
			foreach($this->visible_columns AS $column_name){
				if(isset($this->col2num[$column_name])){

					//map the values
					$colval = $dataRow[$this->col2num[$column_name]];
					if(isset($this->col_map[$column_name])){
						$colval = $this->col_map[$column_name]($colval);
					}
					$visRow[] = $colval;
				}
			}
			fputcsv($outstream, $visRow, $Split, '"');
		}
		rewind($outstream);
		$csv = '';
		while(!feof($outstream)){
			echo(fgets($outstream));
		}
		fclose($outstream);
	}

	//handles any input from the browser and initiates the object
	public function __construct($path ,$sql_query){
		global $TABLE_Classes;
		$this->path = $path;
		$this->sql_query = $sql_query;
		$this->viewIdentifier = crc32('query_'.md5($this->sql_query));
		$this->filtered_sql_query = $this->sql_query;

		if(!isset($_SESSION['query_view'])){
			$_SESSION['query_view'] = array();
		}
		if(!isset($_SESSION['query_view'][$this->viewIdentifier])){
			$_SESSION['query_view'][$this->viewIdentifier] = array();
		}



		//Add filters
		if(isset($_POST[$this->viewIdentifier])){

		}
		if(isset($_GET[$this->viewIdentifier])){

			if(isset($_GET[$this->viewIdentifier]['invert'])){
				foreach($_GET[$this->viewIdentifier]['invert'] AS $column => $invert){
					if($invert == 'inv'){
						$this->invert[$column] = true;
					}
				}
			}
			if(isset($_GET[$this->viewIdentifier]['order'])){
				foreach($_GET[$this->viewIdentifier]['order'] AS $column => $direction){
					if($direction == 'ASC' || $direction == 'DESC'){
						$this->orders[$column] = $direction;
					}
				}
			}
			if(isset($_GET[$this->viewIdentifier]['filter'])){
				foreach($_GET[$this->viewIdentifier]['filter'] AS $column => $value){

					//if we have an array of diffrent values
					if(is_array($value)){
						$vals = $value;
					}else{
						$vals = array($value);
					}

					$col_filts = array();
					foreach($vals AS $val){
						$col_filts[] = $val;
					}
					if(count($col_filts) != 0){
						$this->filters[$column] = $col_filts;
					}
				}
			}
			if(isset($_GET[$this->viewIdentifier]['csv_export'])){
				$this->limit = 0;
				$this->export = 'csv';
			}
			if(isset($_GET[$this->viewIdentifier]['nolimit'])){
				$this->limit = 0;
			}
		}
	}

	//Creates the final query and executes it spits out the export if that was what the user wanted
	public function handle_exec(){
		global $MY_SQL_Handle;

		$filtsql = explode('GROUP BY', $this->filtered_sql_query);
		if(count($this->filters) != 0){
			$filter_sql = array();
			foreach($this->filters AS $column => $vals){
				$filter_or_sql = array();
				foreach($vals AS $user_filter){
					if(isset($this->col_sql_map[$column])){
						$filter_or_sql[] = $this->col_sql_map[$column]($user_filter);
					}else{
						$filter_or_sql[] = $column." = '".$MY_SQL_Handle->real_escape_string($user_filter)."'";
					}
				}
				$ImplodeJoiner = ' OR ';
				if(isset($this->invert[$column]) && $this->invert[$column]){
					$ImplodeJoiner = ' AND ';
					foreach($filter_or_sql AS $n => $comparator){
						$filter_or_sql[$n] = '!('.$comparator.')';
					}
				}
				$sql_or_str = implode($ImplodeJoiner, $filter_or_sql);
				if(count($filter_or_sql) > 1){
					$sql_or_str = '( '.$sql_or_str.' )';
				}
				$filter_sql[] = $sql_or_str;

			}
			if(strpos($this->filtered_sql_query, 'WHERE') === false){
				$filtsql[0] .= ' WHERE ';
			}else {
				$filtsql[0] .= ' AND ';
			}
			$filtsql[0] .= implode(' AND ', $filter_sql);
		}

		$this->filtered_sql_query = implode(' GROUP BY ', $filtsql);

		if(count($this->orders) != 0){
			$order_sql = array();
			foreach($this->orders AS $order => $direction){
				$order_sql[] = $order.' '.$direction;
			}
			$this->filtered_sql_query .= ' ORDER BY '.implode(' , ', $order_sql);
		}

		//we dont apply any limit when we are exporting
		if($this->limit == 0){
			$this->limited_sql_query = $this->filtered_sql_query;
		}else{
			$this->limited_sql_query = $this->filtered_sql_query.' LIMIT '.$this->limit;
		}

		//Do sql execution
		$Result = $MY_SQL_Handle->query($this->limited_sql_query);
		if(!$Result){
			throw new Exception("Data Base query Error: ".$MY_SQL_Handle->error);
		}
		$this->column_metas = $Result->fetch_fields();
		while(($ResultRow = $Result->fetch_row())){
			$this->rows[] = $ResultRow;
		}

		//update estimated_num_rows cache
		if($this->limit == 0){
			$this->estimated_num_rows = count($this->rows);
			$_SESSION['query_view'][$this->viewIdentifier]['estimated_num_rows'] = $this->estimated_num_rows;
		}
		$Result->free();
		//sql done


		//if the user has specified visible fields
		$doautovisible = true;
		if(count($this->visible_columns) > 0){
			$doautovisible = false;
		}

		foreach($this->column_metas AS $num => $column){
			$column_id = $column->table.".".$column->name;
			if($column->table == ""){
				$column_id = $column->name;
			}
			$this->col2num[$column_id] = $num;
			$this->num2col[$num] = $column_id;
			if($doautovisible){
				//If the column name ends with _id we asume it is a system id
				if(substr($column->name, -3) != '_id'){
					$this->visible_columns[] = $column_id;
				}
			}
		}

		$this->distinct();
		if($this->export){
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="export.csv"');

			$this->csv($this->csv_split);
			exit;
		}
	}

	//Gets all the posible values for all collumns in the table
	public function distinct(){
		global $MY_SQL_Handle;

		//get the cached result if it is avalible
		if(isset($_SESSION['query_view'][$this->viewIdentifier]['column_metas'])){
			$this->column_metas = $_SESSION['query_view'][$this->viewIdentifier]['column_metas'];
			$this->estimated_num_rows = $_SESSION['query_view'][$this->viewIdentifier]['estimated_num_rows'];
			return;
		}
		$Result = $MY_SQL_Handle->query($this->sql_query);
		if(!$Result){
			throw new Exception("Data Base query Error: ".$MY_SQL_Handle->error);
		}
		//Initiate distinct array
		foreach($this->column_metas AS $id => $column){
			$this->column_metas[$id]->distinct = array();
			$this->column_metas[$id]->multiple = false;
		}

		//Save each distinct value
		$this->estimated_num_rows = 0;
		while(($ResultRow = $Result->fetch_row())){
			foreach($ResultRow AS $num => $value){
				$column_name = $this->num2col[$num];
				$val = $value;
				if(isset($this->col_map_distinct[$column_name])){
					$val = $this->col_map_distinct[$column_name]($value);
				}
				if(is_array($val)){
					$this->column_metas[$id]->multiple = true;
					foreach($val AS $maped_val){
						$this->column_metas[$num]->distinct[$maped_val] = $maped_val;
					}
				}else{
					if(!is_null($val)){
						$this->column_metas[$num]->distinct[$val] = $value;
					}
				}
			}
			$this->estimated_num_rows++;
		}
		$Result->free();

		//Only show the select box if there is less than 500 distinct values and there is no distict function defined
		foreach($this->column_metas AS $num => $column){
			if(!isset($this->col_map_distinct[$this->num2col[$num]])){
				$select = true;
				$all_numeric = true;
				foreach($column->distinct AS $val){
					if(!is_numeric($val)){
						$all_numeric = false;
					}
				}
				//if there is more than 500 uniq values or if there is more than 15 values and all values are numeric dont use a select box
				if(count($column->distinct) > 500 || ($all_numeric && count($column->distinct) > 15)){
					unset($this->column_metas[$num]->distinct);
				}
			}
			if(isset($this->column_metas[$num]->distinct)){
				asort($this->column_metas[$num]->distinct, SORT_REGULAR);
			}
		}
		$_SESSION['query_view'][$this->viewIdentifier]['column_metas'] = $this->column_metas;
		$_SESSION['query_view'][$this->viewIdentifier]['estimated_num_rows'] = $this->estimated_num_rows;
	}

	//decides what collumns to show and their GUI name
	public function visual_column_names($column_names){
		$this->visible_columns = array();
		foreach($column_names AS $column_name => $VisibleName){
			$this->visible_columns[] = $column_name;
			$this->visible_column_names[$column_name] = $VisibleName;
		}
	}


	//Allows mapping of values
	public function column_maping($column_name, $map_func, $sql_where_func = NULL, $only_distinct = false){
		$this->col_map_distinct[$column_name] = $map_func;
		if(!$only_distinct){
			$this->col_map[$column_name] = $map_func;
		}
		if(isset($sql_where_func)){
			$this->col_sql_map[$column_name] = $sql_where_func;
		}
	}

	//allows mapping of html to columns
	public function html_column_maping($column_name, $map_func){
		$this->html_col_map[$column_name] = $map_func;
	}

	//renders the html table and form
	public function render(){
		$current_url = $this->path;

		$rowCount = count($this->rows);
		$rowMesage = "first ".$rowCount." rows";
		$rowMesage .= " <a href=\"". $_SERVER['REQUEST_URI']."&".$this->viewIdentifier."[nolimit]=1\">Show all rows</a>";
		if($rowCount < $this->limit || $this->limit == 0){
			$rowMesage = "all ".$rowCount." rows";
		}

?>
<div>
<a href="<?= $_SERVER['REQUEST_URI'].'&'.$this->viewIdentifier.'[csv_export]=1' ?>">CSV download <span class="glyphicon glyphicon-download-alt"></span></a> Showing <?=  $rowMesage ?>
</div>
<?php
		echo "		<form class=\"query_view_form\" method=\"GET\">\n";
		list($path, $parms) = explode('?', $this->path, 2);
		$parms = explode('&', $parms);
		foreach($parms AS $parm){
			list($attr, $val) = explode('=', $parm, 2);
			echo '				<input type="hidden" name="'.$attr.'" value="'.$val.'">'."\n";
		}
		echo "\n<table class=\"query_view table table-striped\">\n";
		echo "	<thead>\n";
		echo "			<tr>\n";


		foreach($this->visible_columns AS $column_name){
			if(isset($this->col2num[$column_name])){
				$column = $this->column_metas[$this->col2num[$column_name]];
				$ColVisibleName = $column_name;
				if(isset($this->visible_column_names[$column_name])){
					$ColVisibleName = $this->visible_column_names[$column_name];
				}

				$invert = false;
				if(isset($this->invert[$column_name]) && $this->invert[$column_name]){
					$invert = true;
				}
				echo "					<th>\n";
?>
						<div class="input-group">
							<span onclick="return invert('<?= $this->viewIdentifier.'[invert]['.$column_name.']' ?>')" class="input-group-addon">
								<span <?= $invert?'style="color:red;"':'' ?> class="glyphicon glyphicon-filter"></span>
							</span>
<?php
if(!isset($column->distinct)){
?>
							<input id="qv_<?= $column_name ?>" class="form-control input-sm" value="<?= isset($this->filters[$column_name])?implode('', $this->filters[$column_name]):'' ?>" name="<?= $this->viewIdentifier.'[filter]['.$column_name.']' ?>" type="text" onchange="filter_update(this);">
<?php
}else{
	$multi = '';
	if($this->column_metas[$this->col2num[$column_name]]->multiple || isset($this->filters[$column_name]) && count($this->filters[$column_name]) > 1){
		$multi = 'multiple';
	}
?>
							<select id="qv_<?= $column_name ?>" class="query_view_select_filter form-control input-sm" name="<?= $this->viewIdentifier.'[filter]['.$column_name.'][]' ?>" onchange="filter_update(this);" <?= $multi ?>>
								<option value="---EMPTY---"></option>
<?php foreach($column->distinct AS $maped => $value){
		$selected = '';
		if(isset($this->filters[$column_name]) && in_array($value, $this->filters[$column_name])){
			$selected = 'selected';
		}
		echo("<option value=\"".$value."\" ".$selected.">".$maped."</option>");
	}
?>
							</select>
<?php
}

$order = 'NO';

if(isset($this->orders[$column_name])){
	if($this->orders[$column_name] == 'ASC'){
		$order = 'ASC';
	}elseif($this->orders[$column_name] == 'DESC'){
		$order = 'DESC';
	}
}


?>
						</div>
						<p style="white-space: nowrap;">
							<a href="javascript:void(0);" onclick="return order_rotate('<?= $this->viewIdentifier.'[order]['.$column_name.']' ?>')"><?= $ColVisibleName ?>
<span class="glyphicon
<?php if($order == 'ASC'){
	echo "glyphicon-chevron-down";
}elseif($order == 'DESC'){
	echo "glyphicon-chevron-up";
}else{
	echo "glyphicon-chevron-left";
}
?>
"></span>
</a>
						</p>

						<div class="input-group hidden">
							<div class="checkbox">
								<label>
									<input name="<?= $this->viewIdentifier.'[invert]['.$column_name.']' ?>" value="inv" type="checkbox" <?= $invert?'checked':'' ?>>
								</label>
							</div>
							<label class="radio-inline">
								<input type="radio" name="<?= $this->viewIdentifier.'[order]['.$column_name.']' ?>" value="NO" <?= ($order == 'NO')?'checked':'' ?> onchange="filter_update(this);">
							</label>
							<label class="radio-inline">
								<input type="radio" name="<?= $this->viewIdentifier.'[order]['.$column_name.']' ?>" value="ASC" <?= ($order == 'ASC')?'checked':'' ?> onchange="filter_update(this);">
							</label>
							<label class="radio-inline">
								<input type="radio" name="<?= $this->viewIdentifier.'[order]['.$column_name.']' ?>" value="DESC" <?= ($order == 'DESC')?'checked':'' ?> onchange="filter_update(this);">
							</label>
						</div>
					</th>
<?php
			}
		}
		echo "			</tr>\n";
		echo "	</thead>\n";
		echo '<input type="submit" style="display:none"/>';
		echo "	<tbody>\n";
		foreach($this->rows AS $query_row){
			echo "		<tr>";
			foreach($this->visible_columns AS $column_name){
				if(isset($this->col2num[$column_name])){
					$colval = $query_row[$this->col2num[$column_name]];
					if(isset($this->col_map[$column_name])){
						$colval = $this->col_map[$column_name]($colval);
					}
					if(isset($this->html_col_map[$column_name])){
						echo "<td>".$this->html_col_map[$column_name]($colval, $query_row, $this->col2num)."</td>";
					}else{
						echo "<td>".$colval."</td>";
					}
				}
			}
			echo "</tr>\n";
		}
		echo "	</tbody>\n";
		echo "</table>\n";
		echo "		</form>\n";
	}
}
