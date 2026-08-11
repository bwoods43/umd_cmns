(function (Drupal) {
  Drupal.behaviors.setDefaultSelectByClass = {
    attach: function (context, settings) {
			// check for layout paragraph component form
			const element = document.querySelector('.layout-paragraphs-component-form') 

			// if layout paragraph component form, find data attribute
			if (element) {					

				// set the default only if it's new (on insert)
				const literalPath = element.getAttribute('action'); 
				if (literalPath.includes('/insert')) {			
					const paragraphsWithDefaults = ['edit-layout-paragraphs-component-form-view', 'edit-layout-paragraphs-component-form-card'];
					const customValue = element.getAttribute('data-drupal-selector');

					// check if select option is the proper style option and is empty
					if (paragraphsWithDefaults.includes(customValue)) {
      			const selects = context.querySelectorAll('select.form-element--type-select-multiple');  

      			// cycle through classes to get correct one  
      			selects.forEach(function (select) { 
      				if (select.name == 'behavior_plugins[style_options][example_class][css_class][]' && select.selectedIndex == '-1') { // correct style option and example_class and nothing selected
          			select.selectedIndex = 2; // horizontal padding mobile
        			}
      			});						
					}
					
				}
			}
    }
  };
})(Drupal);

