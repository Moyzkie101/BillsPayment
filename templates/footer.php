<!-- under observation start -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Admin dropdown toggle (fix for .dropdown .dropdown-btn not opening)
    document.querySelectorAll('.dropdown').forEach(function(drop) {
        var btn = drop.querySelector('.dropdown-btn');
        var content = drop.querySelector('.dropdown-content');
        if (!btn || !content) return;

        // Ensure initial state
        content.style.display = content.style.display || 'none';

        // Toggle on button click
        btn.addEventListener('click', function (e) {
            e.stopPropagation(); // prevent other document click handlers from closing immediately
            // Close other dropdowns first
            document.querySelectorAll('.dropdown .dropdown-content').forEach(function(other) {
                if (other !== content) other.style.display = 'none';
            });
            // Toggle this one
            content.style.display = (content.style.display === 'block') ? 'none' : 'block';
            btn.classList.toggle('active');
        });
    });

    // Close any open dropdown when clicking elsewhere
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown .dropdown-content').forEach(function(content) {
            content.style.display = 'none';
        });
        document.querySelectorAll('.dropdown .dropdown-btn').forEach(function(b) {
            b.classList.remove('active');
        });
    });

    // Optional: close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown .dropdown-content').forEach(function(content) {
                content.style.display = 'none';
            });
            document.querySelectorAll('.dropdown .dropdown-btn').forEach(function(b) {
                b.classList.remove('active');
            });
        }
    });
});
</script>
<!-- under observation end -->

<script>
    // Hide and Show Side Menu
    var menubtn = document.getElementById("menu-btn"); // Menu Button
    var sidemenu = document.getElementById("sidemenu"); // Side Menu Div

    // Click outside handler: ignore clicks on the menubtn or its children
    document.addEventListener("click", function(event) {
        try {
            var clickedInsideMenuBtn = menubtn && menubtn.contains(event.target);
            var clickedInsideSidemenu = sidemenu && sidemenu.contains(event.target);

            if (!clickedInsideSidemenu && !clickedInsideMenuBtn) {
                if (sidemenu) {
                    sidemenu.style.animation = "slide-out-to-left 0.5s ease";
                    setTimeout(function() {
                        sidemenu.style.display = "none";
                    }, 450);
                }
            }
        } catch (e) {
            // Fail silently if elements not present
            console.error(e);
        }
    });

    if (menubtn) {
        menubtn.addEventListener("click", function(e){
            // Prevent the document click handler from immediately hiding the menu
            e.stopPropagation();
            try {
                if (!sidemenu || sidemenu.style.display == "none" || sidemenu.style.display == ""){
                    if (sidemenu) {
                        sidemenu.style.animation = "slide-in-from-left 0.5s ease";
                        sidemenu.style.display = "block";
                    }
                } else {
                    sidemenu.style.animation = "slide-out-to-left 0.5s ease";
                    setTimeout(function() {
                        sidemenu.style.display = "none";
                    }, 450);
                }
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Get all the elements (with null checks)
    var parabtn = document.getElementById("para-btn"); // Main Para Button

    //Arrows icon animation
    var paraopen = document.getElementById("open-para"); // Para Div Down Arrow or Expanded
    var paraclosed = document.getElementById("closed-para"); // Para Div Right Arrow or Minimized

    var paraimportnav = document.getElementById("para-import-nav"); // Para Import Div
    var parapostnav = document.getElementById("para-post-nav"); // Para Post Div
    var parareportnav = document.getElementById("para-report-nav"); // Para Report Div
    

    var paraimportbtn = document.getElementById("para-import-btn"); // Para Import Btn
    var parapostbtn = document.getElementById("para-post-btn"); // Para Post Btn
    var parareportbtn = document.getElementById("para-report-btn"); // Para Report Btn
    

    var actionreportbtn = document.getElementById("action-report-btn"); // Action Report Btn
    var actionreportnav = document.getElementById("action-report-nav"); // Action Report Div

    // Sub-elements
    var paraopenimport = document.getElementById("open-para-import"); // Para Import Div Down Arrow or Expanded
    var paraclosedimport = document.getElementById("closed-para-import"); // Para Import Div Right Arrow or Minimized
    var paraopenpost = document.getElementById("open-para-post"); // Para Post Div Down Arrow or Expanded
    var paraclosedpost = document.getElementById("closed-para-post"); // Para Post Div Right Arrow or Minimized
    var paraopenreport = document.getElementById("open-para-report"); // Para Report Div Down Arrow or Expanded
    var paraclosedreport = document.getElementById("closed-para-report"); // Para Report Div Right Arrow or Minimized
    var actionopenreport = document.getElementById("open-action-report"); // Action Report Div Down Arrow or Expanded
    var actionclosedreport = document.getElementById("closed-action-report"); // Action Report Div Right Arrow or Minimized



    // soa Get all the elements (with null checks) Bills Payment SOA
    var soabtn = document.getElementById("soa-btn"); // Main soa Button

    //soa Arrows icon animation
    var soaopen = document.getElementById("open-soa"); // soa Div Down Arrow or Expanded
    var soaclosed = document.getElementById("closed-soa"); // soa Div Right Arrow or Minimized

    var soacreatenav = document.getElementById("soa-create-nav"); // soa create Div
    var soareviewnav = document.getElementById("soa-review-nav"); // soa review Div
    var soaapprovalnav = document.getElementById("soa-approval-nav"); // soa approval Div
    var soareportnav = document.getElementById("soa-report-nav"); // soa report Div

    var soacreatebtn = document.getElementById("soa-create-btn"); // soa create Btn
    var soareviewbtn = document.getElementById("soa-review-btn"); // soa review Btn
    var soaapprovalbtn = document.getElementById("soa-approval-btn"); // soa approval Btn
    var soareportbtn = document.getElementById("soa-report-btn"); // soa report Btn

    // soa Sub-elements
    var soaopencreate = document.getElementById("open-soa-create"); // soa create Div Down Arrow or Expanded
    var soaclosedcreate = document.getElementById("closed-soa-create"); // soa create Div Right Arrow or Minimized
    var soaopenreview = document.getElementById("open-soa-review"); // soa review Div Down Arrow or Expanded
    var soaclosedreview = document.getElementById("closed-soa-review"); // soa review Div Right Arrow or Minimized
    var soaopenapproval = document.getElementById("open-soa-approval"); // soa approval Div Down Arrow or Expanded
    var soaclosedapproval = document.getElementById("closed-soa-approval"); // soa approval Div Right Arrow or Minimized
    var soaopenreport = document.getElementById("open-soa-report"); // soa report Div Down Arrow or Expanded
    var soaclosedreport = document.getElementById("closed-soa-report"); // soa report Div Right Arrow or Minimized



    // Set Get all the elements (with null checks) Maintenance
    var setbtn = document.getElementById("set-btn"); // Main Set Button

    //Set Arrows icon animation
    var setopen = document.getElementById("open-set"); // set Div Down Arrow or Expanded
    var setclosed = document.getElementById("closed-set"); // set Div Right Arrow or Minimized

    var setmaintenancenav = document.getElementById("set-maintenance-nav"); // set maintenance Div

    var setmaintenancebtn = document.getElementById("set-maintenance-btn"); // Set Maintenance Btn

    // Set Sub-elements
    var setopenmaintenance = document.getElementById("open-set-maintenance"); // set maintenance Div Down Arrow or Expanded
    var setclosedmaintenance = document.getElementById("closed-set-maintenance"); // set maintenance Div Right Arrow or Minimized

    // Initialize all dropdown states
    function initializeDropdowns() {
        // Set initial states for all elements
        if (paraimportbtn) paraimportbtn.style.display = "none";
        if (paraimportnav) paraimportnav.style.display = "none";

        if (parapostbtn) parapostbtn.style.display = "none";
        if (parapostnav) parapostnav.style.display = "none";

        if (parareportbtn) parareportbtn.style.display = "none";
        if (parareportnav) parareportnav.style.display = "none";

        if (soacreatebtn) soacreatebtn.style.display = "none";
        if (soacreatenav) soacreatenav.style.display = "none";
        if (soareviewbtn) soareviewbtn.style.display = "none";
        if (soareviewnav) soareviewnav.style.display = "none";
        if (soaapprovalbtn) soaapprovalbtn.style.display = "none";
        if (soaapprovalnav) soaapprovalnav.style.display = "none";
        if (soareportbtn) soareportbtn.style.display = "none";
        if (soareportnav) soareportnav.style.display = "none";

        if (setmaintenancebtn) setmaintenancebtn.style.display = "none";
        if (setmaintenancenav) setmaintenancenav.style.display = "none";

        if (actionreportbtn) actionreportbtn.style.display = "none";
        if (actionreportnav) actionreportnav.style.display = "none";
        
        // Set arrow states
        if (paraopen) paraopen.style.display = "none";
        if (paraclosed) paraclosed.style.display = "block";
        if (paraopenimport) paraopenimport.style.display = "none";
        if (paraclosedimport) paraclosedimport.style.display = "block";
        if (paraopenpost) paraopenpost.style.display = "none";
        if (paraclosedpost) paraclosedpost.style.display = "block";
        if (paraopenreport) paraopenreport.style.display = "none";
        if (paraclosedreport) paraclosedreport.style.display = "block";

        
        if (soaopen) soaopen.style.display = "none";
        if (soaclosed) soaclosed.style.display = "block";
        if (soaopencreate) soaopencreate.style.display = "none";
        if (soaclosedcreate) soaclosedcreate.style.display = "block";
        if (soaopenreview) soaopenreview.style.display = "none";
        if (soaclosedreview) soaclosedreview.style.display = "block";
        if (soaopenapproval) soaopenapproval.style.display = "none";
        if (soaclosedapproval) soaclosedapproval.style.display = "block";
        if (soaopenreport) soaopenreport.style.display = "none";
        if (soaclosedreport) soaclosedreport.style.display = "block";
        

        if (setopen) setopen.style.display = "none";
        if (setclosed) setclosed.style.display = "block";
        if (setopenmaintenance) setopenmaintenance.style.display = "none";
        if (setclosedmaintenance) setclosedmaintenance.style.display = "block";

        if (actionopenreport) actionopenreport.style.display = "none";
        if (actionclosedreport) actionclosedreport.style.display = "block";
    }

    // Call initialization when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeDropdowns();
    });

    // Main Billspayment dropdown handler
    if (parabtn) {
        parabtn.addEventListener("click", function(){ 
            // Get all available sub-menu buttons (only those that exist in DOM)
            var availableSubMenus = [];
            var availableSubNavs = [];
            
            // Check which sub-menus exist based on user permissions
            if (paraimportbtn) {
                availableSubMenus.push(paraimportbtn);
                if (paraimportnav) availableSubNavs.push(paraimportnav);
            }
            if (parapostbtn) {
                availableSubMenus.push(parapostbtn);
                if (parapostnav) availableSubNavs.push(parapostnav);
            }
            if (parareportbtn) {
                availableSubMenus.push(parareportbtn);
                if (parareportnav) availableSubNavs.push(parareportnav);
            }
            if (actionreportbtn) {
                availableSubMenus.push(actionreportbtn);
                if (actionreportnav) availableSubNavs.push(actionreportnav);
            }
            
            // Check if any sub-menu is currently visible
            var isAnyVisible = false;
            for (var i = 0; i < availableSubMenus.length; i++) {
                if (availableSubMenus[i].style.display !== "none" && availableSubMenus[i].style.display !== "") {
                    isAnyVisible = true;
                    break;
                }
            }
            
            if (!isAnyVisible) {
                // Show available sub-menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-in-from-top 0.8s ease";
                    menu.style.display = "flex";
                });
                
                // Update arrows
                if (paraopen) paraopen.style.display = "block";       
                if (paraclosed) paraclosed.style.display = "none";
                
            } else {
                // Hide all sub-menus and their children
                if (paraopen) paraopen.style.display = "none";
                if (paraclosed) paraclosed.style.display = "block";
                
                // Hide child navigation menus first
                availableSubNavs.forEach(function(nav) {
                    nav.style.display = "none";
                });
                
                // Reset child arrows
                if (paraopenimport) paraopenimport.style.display = "none";
                if (paraclosedimport) paraclosedimport.style.display = "block";
                if (paraopenpost) paraopenpost.style.display = "none";
                if (paraclosedpost) paraclosedpost.style.display = "block";
                if (paraopenreport) paraopenreport.style.display = "none";
                if (paraclosedreport) paraclosedreport.style.display = "block";
                if (actionopenreport) actionopenreport.style.display = "none";
                if (actionclosedreport) actionclosedreport.style.display = "block";
                
                // Animate out parent menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-out-to-top 0.5s ease";
                });
                
                setTimeout(function() {
                    availableSubMenus.forEach(function(menu) {
                        menu.style.display = "none";
                    });
                }, 450);
            }
        });
    }

    // Billspayment Import dropdown handler
    if (paraimportbtn) {
        paraimportbtn.addEventListener("click", function(){ 
            var isHidden = !paraimportnav || paraimportnav.style.display === "none" || paraimportnav.style.display === "";
            
            if (isHidden) {
                if (paraimportnav) {
                    paraimportnav.style.animation = "slide-in-from-top 0.8s ease";
                    paraimportnav.style.display = "block";
                }
                if (paraopenimport) paraopenimport.style.display = "block";
                if (paraclosedimport) paraclosedimport.style.display = "none";
            } else {
                if (paraopenimport) paraopenimport.style.display = "none";
                if (paraclosedimport) paraclosedimport.style.display = "block";
                if (paraimportnav) {
                    paraimportnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        paraimportnav.style.display = "none";
                    }, 450);
                }
            }
        });
    }
    
    // Billspayment Post dropdown handler
    if (parapostbtn) {
        parapostbtn.addEventListener("click", function(){ 
            var isHidden = !parapostnav || parapostnav.style.display === "none" || parapostnav.style.display === "";
            
            if (isHidden) {
                if (parapostnav) {
                    parapostnav.style.animation = "slide-in-from-top 0.8s ease";
                    parapostnav.style.display = "block";
                }
                if (paraopenpost) paraopenpost.style.display = "block";
                if (paraclosedpost) paraclosedpost.style.display = "none";
            } else {
                if (paraopenpost) paraopenpost.style.display = "none";
                if (paraclosedpost) paraclosedpost.style.display = "block";
                if (parapostnav) {
                    parapostnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        parapostnav.style.display = "none";
                    }, 450);
                }
            }
        });
    }
    // Billspayment Report dropdown handler
    if (parareportbtn) {
        parareportbtn.addEventListener("click", function(){ 
            var isHidden = !parareportnav || parareportnav.style.display === "none" || parareportnav.style.display === "";
            
            if (isHidden) {
                if (parareportnav) {
                    parareportnav.style.animation = "slide-in-from-top 0.8s ease";
                    parareportnav.style.display = "block";
                }
                if (paraopenreport) paraopenreport.style.display = "block";
                if (paraclosedreport) paraclosedreport.style.display = "none";
            } else {
                if (parareportnav) {
                    parareportnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        parareportnav.style.display = "none";
                    }, 450);
                }
                if (paraopenreport) paraopenreport.style.display = "none";
                if (paraclosedreport) paraclosedreport.style.display = "block";
            }
        });
    }

    // Action Report dropdown handler
    if (actionreportbtn) {
        actionreportbtn.addEventListener("click", function(){ 
            var isHidden = !actionreportnav || actionreportnav.style.display === "none" || actionreportnav.style.display === "";
            
            if (isHidden) {
                if (actionreportnav) {
                    actionreportnav.style.animation = "slide-in-from-top 0.8s ease";
                    actionreportnav.style.display = "block";
                }
                if (actionopenreport) actionopenreport.style.display = "block";
                if (actionclosedreport) actionclosedreport.style.display = "none";
            } else {
                if (actionreportnav) {
                    actionreportnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        actionreportnav.style.display = "none";
                    }, 450);
                }
                if (actionopenreport) actionopenreport.style.display = "none";
                if (actionclosedreport) actionclosedreport.style.display = "block";
            }
        });
    }

    // MAA (Bookkeeper) dropdown handler
    var maabtn = document.getElementById("maa-btn");
    var maaopen = document.getElementById("open-maa");
    var maaclosed = document.getElementById("closed-maa");
    var maanav = document.getElementById("maa-nav");

    if (maabtn && maaopen && maaclosed && maanav) {
        // Initialize MAA dropdown
        maanav.style.display = "none";
        maaopen.style.display = "none";
        maaclosed.style.display = "block";
        
        maabtn.addEventListener("click", function(){
            var isHidden = maanav.style.display === "none" || maanav.style.display === "";
            
            if (isHidden) {
                maaopen.style.display = "block";
                maaclosed.style.display = "none";
                maanav.style.display = "block";
                maanav.style.animation = "slide-in-from-top 0.8s ease";
            } else {
                maanav.style.animation = "slide-out-to-top 0.5s ease";
                setTimeout(function() {
                    maanav.style.display = "none";
                }, 450);
                maaopen.style.display = "none";
                maaclosed.style.display = "block";
            }
        });
    }

    // Additional handlers for other menu items (GLE, MSTRFL, RECON) if they exist
    var glebtn = document.getElementById("gle-btn");
    var gleopen = document.getElementById("open-gle");
    var gleclosed = document.getElementById("closed-gle");
    var glenav = document.getElementById("gle-nav");

    if (glebtn && gleopen && gleclosed && glenav) {
        glenav.style.display = "none";
        gleopen.style.display = "none";
        gleclosed.style.display = "block";
        
        glebtn.addEventListener("click", function(){
            var isHidden = glenav.style.display === "none" || glenav.style.display === "";
            
            if (isHidden) {
                glenav.style.animation = "slide-in-from-top 0.8s ease";
                gleopen.style.display = "block";
                gleclosed.style.display = "none";
                glenav.style.display = "block";
            } else {
                gleopen.style.display = "none";
                gleclosed.style.display = "block";
                glenav.style.animation = "slide-out-to-top 0.5s ease";
                setTimeout(function() {
                    glenav.style.display = "none";
                }, 450);
            }
        });
    }

    var mstrfl = document.getElementById("mstrfl-btn");
    var mstrflopen = document.getElementById("open-mstrfl");
    var mstrflclosed = document.getElementById("closed-mstrfl");
    var mstrflnav = document.getElementById("mstrfl-nav");

    if (mstrfl && mstrflopen && mstrflclosed && mstrflnav) {
        mstrflnav.style.display = "none";
        mstrflopen.style.display = "none";
        mstrflclosed.style.display = "block";
        
        mstrfl.addEventListener("click", function(){
            var isHidden = mstrflnav.style.display === "none" || mstrflnav.style.display === "";
            
            if (isHidden) {
                mstrflnav.style.animation = "slide-in-from-top 0.8s ease";
                mstrflopen.style.display = "block";
                mstrflclosed.style.display = "none";
                mstrflnav.style.display = "block";
            } else {
                mstrflnav.style.animation = "slide-out-to-top 0.5s ease";
                setTimeout(function() {
                    mstrflnav.style.display = "none";
                }, 450);
                mstrflopen.style.display = "none";
                mstrflclosed.style.display = "block";
            }
        });
    }

    var recon = document.getElementById("recon-btn");
    var reconopen = document.getElementById("open-recon");
    var reconclosed = document.getElementById("closed-recon");
    var reconnav = document.getElementById("recon-nav");

    if (recon && reconopen && reconclosed && reconnav) {
        reconnav.style.display = "none";
        reconopen.style.display = "none";
        reconclosed.style.display = "block";
        
        recon.addEventListener("click", function(){
            var isHidden = reconnav.style.display === "none" || reconnav.style.display === "";
            
            if (isHidden) {
                reconnav.style.animation = "slide-in-from-top 0.8s ease";
                reconopen.style.display = "block";
                reconclosed.style.display = "none";
                reconnav.style.display = "block";
            } else {
                reconnav.style.animation = "slide-out-to-top 0.5s ease";
                setTimeout(function() {
                    reconnav.style.display = "none";
                }, 450);
                reconopen.style.display = "none";
                reconclosed.style.display = "block";
            }
        });
    }

    // Main-menu Bills Payment SOA dropdown handler
    if (soabtn) {
        soabtn.addEventListener("click", function(){ 
            // Get all available sub-menu buttons (only those that exist in DOM)
            var availableSubMenus = [];
            var availableSubNavs = [];
            
            // Check which sub-menus exist based on user permissions
            if (soacreatebtn) {
                availableSubMenus.push(soacreatebtn);
                if (soacreatenav) availableSubNavs.push(soacreatenav);
            }
            if (soareviewbtn) {
                availableSubMenus.push(soareviewbtn);
                if (soareviewnav) availableSubNavs.push(soareviewnav);
            }
            if (soaapprovalbtn) {
                availableSubMenus.push(soaapprovalbtn);
                if (soaapprovalnav) availableSubNavs.push(soaapprovalnav);
            }
            if (soareportbtn) {
                availableSubMenus.push(soareportbtn);
                if (soareportnav) availableSubNavs.push(soareportnav);
            }
            
            // Check if any sub-menu is currently visible
            var isAnyVisible = false;
            for (var i = 0; i < availableSubMenus.length; i++) {
                if (availableSubMenus[i].style.display !== "none" && availableSubMenus[i].style.display !== "") {
                    isAnyVisible = true;
                    break;
                }
            }
            
            if (!isAnyVisible) {
                // Show available sub-menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-in-from-top 0.8s ease";
                    menu.style.display = "flex";
                });
                
                // Update arrows
                if (soaopen) soaopen.style.display = "block";       
                if (soaclosed) soaclosed.style.display = "none";
                
            } else {
                // Hide all sub-menus and their children
                if (soaopen) soaopen.style.display = "none";
                if (soaclosed) soaclosed.style.display = "block";
                
                // Hide child navigation menus first
                availableSubNavs.forEach(function(nav) {
                    nav.style.display = "none";
                });
                
                // Reset child arrows
                if (soaopencreate) soaopencreate.style.display = "none";
                if (soaclosedcreate) soaclosedcreate.style.display = "block";
                if (soaopenreview) soaopenreview.style.display = "none";
                if (soaclosedreview) soaclosedreview.style.display = "block";
                if (soaopenapproval) soaopenapproval.style.display = "none";
                if (soaclosedapproval) soaclosedapproval.style.display = "block";
                if (soaopenreport) soaopenreport.style.display = "none";
                if (soaclosedreport) soaclosedreport.style.display = "block";
                
                // Animate out parent menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-out-to-top 0.5s ease";
                });
                
                setTimeout(function() {
                    availableSubMenus.forEach(function(menu) {
                        menu.style.display = "none";
                    });
                }, 450);
            }
        });
    }

    // Sub-menu Create dropdown handler (only if exists)
    if (soacreatebtn) {
        soacreatebtn.addEventListener("click", function(){ 
            var isHidden = !soacreatenav || soacreatenav.style.display === "none" || soacreatenav.style.display === "";
            
            if (isHidden) {
                if (soacreatenav) {
                    soacreatenav.style.animation = "slide-in-from-top 0.8s ease";
                    soacreatenav.style.display = "block";
                }
                if (soaopencreate) soaopencreate.style.display = "block";
                if (soaclosedcreate) soaclosedcreate.style.display = "none";
            } else {
                if (soaopencreate) soaopencreate.style.display = "none";
                if (soaclosedcreate) soaclosedcreate.style.display = "block";
                if (soacreatenav) {
                    soacreatenav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        soacreatenav.style.display = "none";
                    }, 450);
                }
            }
        });
    }

    // Sub-menu Review dropdown handler (only if exists)
    if (soareviewbtn) {
        soareviewbtn.addEventListener("click", function(){ 
            var isHidden = !soareviewnav || soareviewnav.style.display === "none" || soareviewnav.style.display === "";
            
            if (isHidden) {
                if (soareviewnav) {
                    soareviewnav.style.animation = "slide-in-from-top 0.8s ease";
                    soareviewnav.style.display = "block";
                }
                if (soaopenreview) soaopenreview.style.display = "block";
                if (soaclosedreview) soaclosedreview.style.display = "none";
            } else {
                if (soaopenreview) soaopenreview.style.display = "none";
                if (soaclosedreview) soaclosedreview.style.display = "block";
                if (soareviewnav) {
                    soareviewnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        soareviewnav.style.display = "none";
                    }, 450);
                }
            }
        });
    }
    // Sub-menu approval dropdown handler (only if exists)
    if (soaapprovalbtn) {
        soaapprovalbtn.addEventListener("click", function(){ 
            var isHidden = !soaapprovalnav || soaapprovalnav.style.display === "none" || soaapprovalnav.style.display === "";
            
            if (isHidden) {
                if (soaapprovalnav) {
                    soaapprovalnav.style.animation = "slide-in-from-top 0.8s ease";
                    soaapprovalnav.style.display = "block";
                }
                if (soaopenapproval) soaopenapproval.style.display = "block";
                if (soaclosedapproval) soaclosedapproval.style.display = "none";
            } else {
                if (soaopenapproval) soaopenapproval.style.display = "none";
                if (soaclosedapproval) soaclosedapproval.style.display = "block";
                if (soaapprovalnav) {
                    soaapprovalnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        soaapprovalnav.style.display = "none";
                    }, 450);
                }
            }
        });
    }

    // Sub-menu Report dropdown handler (always exists)
    if (soareportbtn) {
        soareportbtn.addEventListener("click", function(){ 
            var isHidden = !soareportnav || soareportnav.style.display === "none" || soareportnav.style.display === "";
            
            if (isHidden) {
                if (soareportnav) {
                    soareportnav.style.animation = "slide-in-from-top 0.8s ease";
                    soareportnav.style.display = "block";
                }
                if (soaopenreport) soaopenreport.style.display = "block";
                if (soaclosedreport) soaclosedreport.style.display = "none";
            } else {
                if (soareportnav) {
                    soareportnav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        soareportnav.style.display = "none";
                    }, 450);
                }
                if (soaopenreport) soaopenreport.style.display = "none";
                if (soaclosedreport) soaclosedreport.style.display = "block";
            }
        });
    }

    // Main-menu Maintenance dropdown handler
    if (setbtn) {
        setbtn.addEventListener("click", function(){ 
            // Get all available sub-menu buttons (only those that exist in DOM)
            var availableSubMenus = [];
            var availableSubNavs = [];
            
            // Check which sub-menus exist based on user permissions
            if (setmaintenancebtn) {
                availableSubMenus.push(setmaintenancebtn);
                if (setmaintenancenav) availableSubNavs.push(setmaintenancenav);
            }
            
            // Check if any sub-menu is currently visible
            var isAnyVisible = false;
            for (var i = 0; i < availableSubMenus.length; i++) {
                if (availableSubMenus[i].style.display !== "none" && availableSubMenus[i].style.display !== "") {
                    isAnyVisible = true;
                    break;
                }
            }
            
            if (!isAnyVisible) {
                // Show available sub-menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-in-from-top 0.8s ease";
                    menu.style.display = "flex";
                });
                
                // Update arrows
                if (setopen) setopen.style.display = "block";       
                if (setclosed) setclosed.style.display = "none";
                
            } else {
                // Hide all sub-menus and their children
                if (setopen) setopen.style.display = "none";
                if (setclosed) setclosed.style.display = "block";
                
                // Hide child navigation menus first
                availableSubNavs.forEach(function(nav) {
                    nav.style.display = "none";
                });
                
                // Reset child arrows
                if (setopenmaintenance) setopenmaintenance.style.display = "none";
                if (setclosedmaintenance) setclosedmaintenance.style.display = "block";
                
                // Animate out parent menus
                availableSubMenus.forEach(function(menu) {
                    menu.style.animation = "slide-out-to-top 0.5s ease";
                });
                
                setTimeout(function() {
                    availableSubMenus.forEach(function(menu) {
                        menu.style.display = "none";
                    });
                }, 450);
            }
        });
    }

    // Sub-menu Accounts dropdown handler
    if (setmaintenancebtn) {
        setmaintenancebtn.addEventListener("click", function(){ 
            var isHidden = !setmaintenancenav || setmaintenancenav.style.display === "none" || setmaintenancenav.style.display === "";
            
            if (isHidden) {
                if (setmaintenancenav) {
                    setmaintenancenav.style.animation = "slide-in-from-top 0.8s ease";
                    setmaintenancenav.style.display = "block";
                }
                if (setopenmaintenance) setopenmaintenance.style.display = "block";
                if (setclosedmaintenance) setclosedmaintenance.style.display = "none";
            } else {
                if (setopenmaintenance) setopenmaintenance.style.display = "none";
                if (setclosedmaintenance) setclosedmaintenance.style.display = "block";
                if (setmaintenancenav) {
                    setmaintenancenav.style.animation = "slide-out-to-top 0.5s ease";
                    setTimeout(function() {
                        setmaintenancenav.style.display = "none";
                    }, 450);
                }
            }
        });
    }

</script>

<!-- <script>
// Table functionality
let currentPage = 1;
let rowsPerPage = 50;
let filteredData = [];

document.addEventListener('DOMContentLoaded', function() {
    // Initialize table
    initializeTable();
    
    // Search functionality
    document.getElementById('tableSearch').addEventListener('input', function() {
        filterTable();
    });
    
    // Filter functionality
    document.getElementById('statusFilter').addEventListener('change', function() {
        filterTable();
    });
    
    document.getElementById('partnerFilter').addEventListener('change', function() {
        filterTable();
    });
});

function initializeTable() {
    const tableBody = document.getElementById('tableBody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    filteredData = rows;
    updatePagination();
}

function filterTable() {
    const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const partnerFilter = document.getElementById('partnerFilter').value;
    
    const tableBody = document.getElementById('tableBody');
    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    
    filteredData = allRows.filter(row => {
        if (row.querySelector('.no-data')) return false;
        
        const rowText = row.textContent.toLowerCase();
        const rowStatus = row.getAttribute('data-status');
        const rowPartner = row.getAttribute('data-partner');
        
        const matchesSearch = rowText.includes(searchTerm);
        const matchesStatus = !statusFilter || rowStatus === statusFilter || (statusFilter === 'normal' && !['*', '**', '***'].includes(rowStatus));
        const matchesPartner = !partnerFilter || rowPartner === partnerFilter;
        
        return matchesSearch && matchesStatus && matchesPartner;
    });
    
    currentPage = 1;
    displayTable();
    updatePagination();
}

function displayTable() {
    const tableBody = document.getElementById('tableBody');
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    
    // Hide all rows
    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    allRows.forEach(row => row.style.display = 'none');
    
    // Show filtered rows for current page
    const rowsToShow = filteredData.slice(startIndex, endIndex);
    rowsToShow.forEach(row => row.style.display = '');
    
    // Show no data message if no results
    if (filteredData.length === 0) {
        if (!document.querySelector('.no-data-row')) {
            const noDataRow = document.createElement('tr');
            noDataRow.className = 'no-data-row';
            noDataRow.innerHTML = '<td colspan="9" class="no-data">No data matches your search criteria</td>';
            tableBody.appendChild(noDataRow);
        }
    } else {
        const noDataRow = document.querySelector('.no-data-row');
        if (noDataRow) {
            noDataRow.remove();
        }
    }
    
    updatePaginationInfo();
}

function updatePagination() {
    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
    
    document.getElementById('prevBtn').disabled = currentPage === 1;
    document.getElementById('nextBtn').disabled = currentPage === totalPages || totalPages === 0;
    
    // Update page numbers
    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';
    
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'pagination-btn' + (i === currentPage ? ' active' : '');
        pageBtn.textContent = i;
        pageBtn.onclick = () => goToPage(i);
        pageNumbers.appendChild(pageBtn);
    }
    
    displayTable();
}

function updatePaginationInfo() {
    const start = filteredData.length === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
    const end = Math.min(currentPage * rowsPerPage, filteredData.length);
    const total = filteredData.length;
    
    document.getElementById('paginationInfo').textContent = 
        `Showing ${start}-${end} of ${total} entries`;
}

function changePage(direction) {
    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
    const newPage = currentPage + direction;
    
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        updatePagination();
    }
}

function goToPage(page) {
    currentPage = page;
    updatePagination();
}

function refreshTable() {
    location.reload();
}

function exportTable() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "ID,Partner,Transaction Date,Reference Number,Amount Paid,Charge to Partner,Charge to Customer,Status\n";
    
    filteredData.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td')).slice(0, 8); // Exclude actions column
        const rowData = cells.map(cell => {
            let text = cell.textContent.trim();
            // Remove currency symbols and format numbers
            if (cell.classList.contains('amount')) {
                text = text.replace(/[^\d.-]/g, '');
            }
            return `"${text}"`;
        }).join(',');
        csvContent += rowData + "\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "bills_payment_data.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function viewTransaction(id) {
    // Implementation for viewing transaction details
    alert(`Viewing transaction ID: ${id}`);
}

function editTransaction(id) {
    // Implementation for editing transaction
    alert(`Editing transaction ID: ${id}`);
}
</script> -->