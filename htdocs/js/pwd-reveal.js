// JavaScript Document

$(document).ready(function(){
	$(".pwd-reveal").on("mousedown", function(){
		var o = $(this);
		var f = $("#"+o.data("for"));
		$(f).removeClass("pwd-hide");
		$(f).addClass("pwd-open");		
	}).on("mouseup", function(){
		var o = $(this);
		var f = $("#"+o.data("for"));
		$(f).removeClass("pwd-open");
		$(f).addClass("pwd-hide");		
	});
	
});