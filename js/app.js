// POTA Activation Tracker Main JS Logic

document.addEventListener('DOMContentLoaded', function() {
    
    // ----------------------------------------------------
    // 1. Mobile Menu Toggle
    // ----------------------------------------------------
    const burgerMenu = document.querySelector('.burger-menu');
    const navMenu = document.querySelector('.nav-menu');
    
    if (burgerMenu && navMenu) {
        burgerMenu.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            // Animate burger bars
            const bars = burgerMenu.querySelectorAll('.burger-bar');
            if (navMenu.classList.contains('active')) {
                bars[0].style.transform = 'rotate(-45deg) translate(-5px, 5px)';
                bars[1].style.opacity = '0';
                bars[2].style.transform = 'rotate(45deg) translate(-5px, -5px)';
            } else {
                bars[0].style.transform = 'none';
                bars[1].style.opacity = '1';
                bars[2].style.transform = 'none';
            }
        });
    }

    // ----------------------------------------------------
    // 2. Hero Image Slider (Homepage)
    // ----------------------------------------------------
    const slides = document.querySelectorAll('.slide');
    const prevBtn = document.querySelector('.slider-btn.prev');
    const nextBtn = document.querySelector('.slider-btn.next');
    let currentSlide = 0;
    let slideInterval;

    if (slides.length > 0) {
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
        }

        function nextSlide() {
            showSlide(currentSlide + 1);
        }

        function prevSlide() {
            showSlide(currentSlide - 1);
        }

        // Start auto rotation
        slideInterval = setInterval(nextSlide, 5000);

        // Manual controls
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                clearInterval(slideInterval);
                nextSlide();
                slideInterval = setInterval(nextSlide, 5000);
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                clearInterval(slideInterval);
                prevSlide();
                slideInterval = setInterval(nextSlide, 5000);
            });
        }
    }

    // ----------------------------------------------------
    // 3. Dynamic POTA.app API Lookup
    // ----------------------------------------------------
    const parkRefInput = document.getElementById('park_reference');
    const lookupStatus = document.getElementById('lookup-status');
    const parkNameInput = document.getElementById('park_name');
    const latInput = document.getElementById('latitude');
    const lonInput = document.getElementById('longitude');

    if (parkRefInput) {
        let lookupTimeout;

        parkRefInput.addEventListener('input', function() {
            clearTimeout(lookupTimeout);
            const ref = parkRefInput.value.trim().toUpperCase();
            
            // Format check (e.g. US-0001, PL-0023, SP-0100)
            const regex = /^[A-Z0-9]+-[0-9A-Z]+$/;
            
            if (!ref) {
                showStatus('', '');
                return;
            }

            if (!regex.test(ref)) {
                showStatus('Invalid reference format. Use e.g. US-0001', 'danger');
                return;
            }

            showStatus('Looking up park on POTA.app...', 'info');

            // Debounce the API call
            lookupTimeout = setTimeout(function() {
                fetch(`https://api.pota.app/park/${ref}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response error');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.name) {
                            parkNameInput.value = data.name;
                            if (latInput) latInput.value = data.latitude || '';
                            if (lonInput) lonInput.value = data.longitude || '';
                            showStatus(`Found: ${data.name} (${data.locationName || ''})`, 'success');
                        } else {
                            showStatus('Park not found in POTA database.', 'danger');
                        }
                    })
                    .catch(err => {
                        console.error('API Error:', err);
                        showStatus('API connection error. Enter park name manually.', 'danger');
                    });
            }, 600);
        });

        function showStatus(message, type) {
            if (!lookupStatus) return;
            lookupStatus.innerHTML = message;
            lookupStatus.className = 'form-text'; // reset class
            if (type) {
                lookupStatus.classList.add(`text-${type}`);
            }
        }
    }

    // ----------------------------------------------------
    // 4. Equipment Profile Template Loader
    // ----------------------------------------------------
    const profileSelect = document.getElementById('equipment_profile_id');
    const transceiverInput = document.getElementById('transceiver');
    const antennaInput = document.getElementById('antenna');
    const powerSourceInput = document.getElementById('power_source');
    const powerWattsInput = document.getElementById('power_watts');
    const additionalEquipmentInput = document.getElementById('additional_equipment');

    if (profileSelect) {
        profileSelect.addEventListener('change', function() {
            const selectedOption = profileSelect.options[profileSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                // Populate fields from data attributes of selected option
                if (transceiverInput) transceiverInput.value = selectedOption.getAttribute('data-transceiver') || '';
                if (antennaInput) antennaInput.value = selectedOption.getAttribute('data-antenna') || '';
                if (powerSourceInput) powerSourceInput.value = selectedOption.getAttribute('data-power-source') || '';
                if (powerWattsInput) powerWattsInput.value = selectedOption.getAttribute('data-power-watts') || '';
                if (additionalEquipmentInput) additionalEquipmentInput.value = selectedOption.getAttribute('data-additional-equipment') || '';
            }
        });
    }

    // ----------------------------------------------------
    // 5. Activation Image Gallery Thumbnail Swap
    // ----------------------------------------------------
    const mainGalleryImg = document.getElementById('main-gallery-image');
    const thumbnails = document.querySelectorAll('.gallery-thumb');

    if (mainGalleryImg && thumbnails.length > 0) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                // Remove active class from all thumbnails
                thumbnails.forEach(t => t.classList.remove('active'));
                
                // Set active class to current thumbnail
                this.classList.add('active');
                
                // Swap main image src
                const newSrc = this.getAttribute('data-large-src');
                mainGalleryImg.setAttribute('src', newSrc);
            });
        });
    }
});
