
function order_rotate(what_str){
	var orderOptions = document.getElementsByName(what_str);
	//alert("updated filter");
	var checkednum = 0;
	for(var i=0;i<orderOptions.length;i++){
		if(orderOptions[i].checked){
			checkednum = i;
		}
	}
	checkednum++;
	if(checkednum >= orderOptions.length){
		checkednum = 0;
	}
	orderOptions[checkednum].checked = true;
	filter_update(orderOptions[checkednum]);
	return false;
}
function invert(what_str){
	var invertcheck = document.getElementsByName(what_str);
	//alert("updated filter");
	var checked = invertcheck[0].checked;
	if(checked){
		invertcheck[0].checked = false;
	}else{
		invertcheck[0].checked = true;
	}
	filter_update(invertcheck[0]);
	return false;
}
function filter_update(what){

	//We disable any fields that are not active to make the url a bit shorter
	var qv_forms = document.querySelectorAll('.query_view_form');
	for(var i=0;i<qv_forms.length;i++){
		for(var o=0;o<qv_forms[i].length;o++){
			var inp = qv_forms[i][o];
			if(inp.type == 'text'){
				if(inp.value == ""){
					inp.disabled = true;
				}
			}else if(inp.type == 'radio' && inp.checked == true){
				if(inp.value == "NO"){
					inp.disabled = true;
				}
			}else{
				if(inp.tagName == 'SELECT'){
					if(inp.value == "---EMPTY---"){
						inp.disabled = true;
					}
				}
			}
		}
	}

	//Then we just submit the form
	what.form.submit();
}
