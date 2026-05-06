<!-- Modal -->
<div id="myModal" class="modal fade" data-refresh="0">
    <div id="myModal-container" class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button id="myModal-close" type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h2 class="modal-title" id="myModalLabel"></h2>

            </div>
            <div class="modal-body">
                <iframe frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>

    function SetModalHeader(val) {
        $('#myModalLabel').html(val);
    }
    
    function SetModalHeight(val) {
        $("#myModal iframe").attr({
            'height': val});
    }
    
    function SetModalWidth(val) {
        $("#myModal-container").css({
            'width': val});
    }
    
    function SetDataRefresh(val) {
        $("#myModal").attr('data-refresh', val);
        //alert('SetDataRefresh='+val);
    }
    
    function CloseModal() {
        $("#myModal").modal('hide');
    }


</script>