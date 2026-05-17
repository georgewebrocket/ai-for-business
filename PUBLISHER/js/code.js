window.PublisherTheme = (function() {
    var storageKey = 'publisher-theme';
    var darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function getStoredTheme() {
        try {
            return localStorage.getItem(storageKey);
        } catch (error) {
            return null;
        }
    }

    function getTheme() {
        var storedTheme = getStoredTheme();
        if (storedTheme === 'dark' || storedTheme === 'light') {
            return storedTheme;
        }
        return darkQuery && darkQuery.matches ? 'dark' : 'light';
    }

    function updateToggle(theme) {
        var toggles = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < toggles.length; i++) {
            var toggle = toggles[i];
            var icon = toggle.querySelector('.theme-toggle-icon');
            toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            toggle.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            if (icon) {
                icon.className = 'fa theme-toggle-icon ' + (theme === 'dark' ? 'fa-sun-o' : 'fa-moon-o');
            }
        }
    }

    function syncTinyMce(theme) {
        if (!window.tinymce || !tinymce.get) {
            return;
        }

        var editors = tinymce.get();
        for (var i = 0; i < editors.length; i++) {
            var body = editors[i].getBody && editors[i].getBody();
            if (body) {
                body.setAttribute('data-theme', theme);
                body.style.colorScheme = theme;
                body.style.backgroundColor = theme === 'dark' ? '#111827' : '#ffffff';
                body.style.color = theme === 'dark' ? '#e5e7eb' : '#222222';
            }
        }
    }

    function applyTheme(theme, shouldStore) {
        var nextTheme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', nextTheme);
        document.documentElement.style.colorScheme = nextTheme;
        if (shouldStore) {
            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (error) {}
        }
        updateToggle(nextTheme);
        syncTinyMce(nextTheme);
    }

    function setTheme(theme) {
        applyTheme(theme, true);
    }

    function init() {
        applyTheme(getTheme(), false);
        document.addEventListener('click', function(event) {
            var toggle = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
            if (!toggle) {
                return;
            }
            event.preventDefault();
            setTheme(getTheme() === 'dark' ? 'light' : 'dark');
        });

        if (darkQuery && darkQuery.addEventListener) {
            darkQuery.addEventListener('change', function() {
                if (!getStoredTheme()) {
                    setTheme(getTheme());
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        get: getTheme,
        refresh: function() {
            applyTheme(getTheme(), false);
        },
        set: setTheme
    };
})();

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
