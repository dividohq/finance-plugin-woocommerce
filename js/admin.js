

const checkStatuses = ['wc-cancelled', 'wc-refunded'];

jQuery(document).ready(function($) {
    $('#financeStatusModal').dialog({
        autoOpen:false, 
        draggable:false,
        dialogClass: 'wp-dialog',
        create: function () {
            // style fix for WordPress admin
            $('.ui-dialog-titlebar-close').addClass('ui-button');
        }
    });
    const saveBtn = document.getElementsByClassName('save_order')[0];

    const currentStatus = document.getElementById('order_status').value;
    console.log(currentStatus);
    
    saveBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const newStatus = document.getElementById('order_status').value;
        const financeId = document.getElementById('financeId').value;
        
        if(!financeId || newStatus === currentStatus || !checkStatuses.find((e) => e === newStatus)){
            console.log("Nothing to do here");
            return;
        }
        
        console.log("Making status check");
        // make ajax call
        const request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            if(request.readyState === XMLHttpRequest.DONE && request.status === 200){
                $('#financeStatusModal .contents').text(request.responseText);
                $('#financeStatusModal')
                    .dialog({
                        title: 'This is a title'
                    })
                    .dialog('open');
                console.log(request.responseText)
                /*
                if( //ignore ){
                    event.target.trigger('click');
                }
                */
            }
        }
        request.open("GET", statusCheckPath+'?action=woocommerce_finance_status&status='+newStatus+'&id='+financeId);
        request.send();
            // on done either display a modal
            // or trigger click event
        
    })
    
})
    