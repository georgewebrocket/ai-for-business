function ExpandTree(el,getsubgrid) {    
    //event.preventDefault();
    var currentId = $(el).attr('id').substr(1);    
    if ($('#subgrid'+currentId).css('display')=='none') {
        $.ajax({url: getsubgrid+"?id="+currentId,success:function(result){
            $('#subgrid'+currentId).html(result);                
            }});            
        $('#subgrid'+currentId).show();
        $(el).html("<span style=\"font-size: 20px;\" class=\"glyphicon glyphicon-minus\"></span>");
        $(el).removeClass("inline-button tree").addClass("inline-button-selected");
        $('tr#'+currentId+" td").css("font-weight","bold");
    }
    else {
        $('#subgrid'+currentId).hide();
        $(el).html("<span style=\"font-size: 20px;\" class=\"glyphicon glyphicon-plus\"></span>");
        $(el).removeClass("inline-button-selected").addClass("inline-button tree");
        $('tr#'+currentId+" td").css("font-weight","normal");
        //$(el).css("background-color", "rgb(200,200,200)")
    }

} 

$(document).ready(function() {
    
    $('a.modalBtn').click(function() {

        var src = $(this).attr('data-href');
        var height = $(this).attr('data-height') || 700;
        var width = $(this).attr('data-width') || 0;
        var modalTitle = $(this).attr('data-title');
        
        if (width!=0) {
            $("#myModal-container").css('width', width);
        }

        /*$("#myModal iframe").attr({'src':src,
            'height': height,
            'width': '100%'});*/
        $("#myModal iframe").attr({'src':src,
            'width': '100%'});        
        var h = window.innerHeight;    
        h = h - 150;
        /*console.log(height);  */
        h = h < height? h: height;
        $("#myModal iframe").css("height",h+"px");
        $('#myModalLabel').html(modalTitle);
        
        $('#myModal').modal('show'); 
        
    });
    
    $('#myModal-close').on('click', function() { 
        $('#myModalLabel').html("");
        $("#myModal iframe").attr({'src':"blocks/loading.php"});
    });
    
    $('#myModal').on('hidden.bs.modal', function () {
        
        var iRefresh = $(this).attr('data-refresh');
        if (iRefresh=='1') {
            refresh();
            SetDataRefresh('0');
        }
      });
    
    
       
    
    
        
});



$(function() {
    $( ".datepicker" ).datepicker($.datepicker.regional[ "GR" ]);
    });
    


function setModals() {
    
    $('a.modalBtn').click(function() {

        var src = $(this).attr('data-href');
        var height = $(this).attr('data-height') || 700;
        var width = $(this).attr('data-width') || 0;
        var modalTitle = $(this).attr('data-title');
        
        if (width!=0) {
            $("#myModal-container").css('width', width);
        }

        /*$("#myModal iframe").attr({'src':src,
            'height': height,
            'width': '100%'});*/
        $("#myModal iframe").attr({'src':src,
            'width': '100%'});        
        var h = window.innerHeight;    
        h = h - 150;
        /*console.log(height);  */
        h = h < height? h: height;
        $("#myModal iframe").css("height",h+"px");
        $('#myModalLabel').html(modalTitle);
        
        $('#myModal').modal('show'); 
        
    });
    
    $('#myModal-close').on('click', function() { 
        $('#myModalLabel').html("");
        $("#myModal iframe").attr({'src':"blocks/loading.php"});
    });
    
    $('#myModal').on('hidden.bs.modal', function () {
        
        var iRefresh = $(this).attr('data-refresh');
        if (iRefresh=='1') {
            refresh();
            SetDataRefresh('0');
        }
      });
    
    
}