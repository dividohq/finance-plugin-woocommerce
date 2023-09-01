

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

    var statusCheck = true;
    
    saveBtn.addEventListener('click', (event) => {
        if(statusCheck){
            event.preventDefault();
        }else return;

        if(!document.getElementById('financeId')){
            statusCheck = false;
            event.target.click();
            return;
        }

        const newStatus = document.getElementById('order_status').value;
        
        const financeId = document.getElementById('financeId').value;
        
        if(!financeId || newStatus === currentStatus || !checkStatuses.find((e) => e === newStatus)){
            statusCheck = false;
            event.target.click();
            return;
        }
        
        console.log("Making status check");
        // make ajax call
        const request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            if(request.readyState === XMLHttpRequest.DONE && request.status === 200){
                const response = request.responseText;

                $('#financeStatusModal .contents').text(response.message);
                $('#financeStatusModal')
                    .dialog({
                        title: 'This is a title'
                    })
                    .dialog('open');
                console.log(response)
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
    