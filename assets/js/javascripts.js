$(document).ready(function(){
	//when a division is clicked, a control panel will be appear
	//from control panel, user can add more layout or content into the division	
	$("#wpsomeeditor").delegate('div', 'click', function(){
	  wpsome_div_editor($(this).attr("id"));
	  return false; // avoid parents divs if you have nested divs
	});
	
	//when a mouse comes to div it will show it's layout
	$("#wpsomeeditor").delegate('div', 'mouseover', function(){	  	  
	  $(this).siblings().removeClass('wpsomedivhover');
	  $(this).parents().removeClass('wpsomedivhover');
	  $(this).children().removeClass('wpsomedivhover');
      $(this).addClass('wpsomedivhover');
	  return false; // avoid parents divs if you have nested divs
	});
	
	//a mouse leaves a div it will be in it's actual design
	$("#wpsomeeditor").delegate('div', 'mouseout', function(){
      $(this).removeClass('wpsomedivhover');
      //alert($(this).attr("id"));
	  return false; // avoid parents divs if you have nested divs
	});
});

function wpsome_page_width(type)
{
	document.getElementById("container").className = type;
}

function wpsome_div_editor(id)
{
	var editor;
	editor="<a href='#' onclick=\"javascript: wpsome_open_editor_tab('general', '"+id+"'); return false;\">General</a>"; //<li>Add Layout</li><li>Add Content</li></ul>";
	editor+=" | ";
	editor+="<a href='#' onclick=\"javascript: wpsome_open_editor_tab('design', '"+id+"'); return false;\">Design</a>"; //<li>Add Layout</li><li>Add Content</li></ul>";
	editor+=" | ";
	editor+="<a href='#' onclick=\"javascript: wpsome_open_editor_tab('layout', '"+id+"'); return false;\">Layout</a>"; //<li>Add Layout</li><li>Add Content</li></ul>";
	editor+=" | ";
	editor+="<a href='#' onclick=\"javascript: wpsome_open_editor_tab('content', '"+id+"'); return false;\">Content</a>"; //<li>Add Layout</li><li>Add Content</li></ul>";
	
	editor+="<div id='wpsomeeditor_panel_body'></div>";
	document.getElementById("wpsomecommonpanel").innerHTML=editor;
	//alert(id);
}

//when a tab is clicked from editing panel, requested options will be appeared
function wpsome_open_editor_tab(type, id)
{
	var tab_content;
	
	if(type=='general')
	{
		tab_content="<a href='#' onclick=\"javascript: wpsome_remove_div('"+id+"'); return false;\">Remove</a>";
	}
	if(type=='layout')
	{
		tab_content="<a href='#' onclick=\"javascript:wpsome_add_layout('"+id+"', 'row-fluid', ''); return false;\">Add Layout</a>";
	}
	document.getElementById("wpsomeeditor_panel_body").innerHTML = tab_content;
}

function wpsome_remove_div(id)
{
	var element = document.getElementById(id);
    element.parentNode.removeChild(element);
}

function wpsome_add_layout(div, type, spans)
{
	var content = document.getElementById(div).innerHTML;
	content=content+"<div style='background-color:#b0c4de;' class='row-fluid'>";
		content=content+"<div id='"+div+"_1' class='span6'>";
		content=content+"</div>";
		content=content+"<div id='"+div+"_2' class='span6'>";
		content=content+"</div>";
	content=content+"</div>";
	
	document.getElementById(div).innerHTML=content;
}



