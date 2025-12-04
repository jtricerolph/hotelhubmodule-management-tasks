/**
 * Photo Gallery Component
 *
 * Handles photo uploads, galleries, and lightbox
 *
 * @package HotelHub_Management_Tasks
 */

(function($) {
    'use strict';

    let currentGallery = [];
    let currentIndex = 0;

    /**
     * Initialize photo gallery
     */
    function initPhotoGallery() {
        // Click on photo to open lightbox
        $(document).on('click', '.hhmgt-photo-item', function() {
            const $gallery = $(this).closest('.hhmgt-photo-gallery');
            const $photos = $gallery.find('.hhmgt-photo-item');

            currentGallery = [];
            $photos.each(function(index) {
                const src = $(this).find('img').attr('src');
                if (src) {
                    currentGallery.push(src);
                }
                if ($(this).is($photos.eq(index))) {
                    currentIndex = index;
                }
            });

            currentIndex = $photos.index(this);
            openLightbox();
        });

        // Close lightbox
        $(document).on('click', '.hhmgt-lightbox-close, .hhmgt-lightbox-overlay', function(e) {
            if ($(e.target).hasClass('hhmgt-lightbox-overlay') || $(e.target).closest('.hhmgt-lightbox-close').length) {
                closeLightbox();
            }
        });

        // Navigate lightbox
        $(document).on('click', '.hhmgt-lightbox-prev', function() {
            navigateLightbox(-1);
        });

        $(document).on('click', '.hhmgt-lightbox-next', function() {
            navigateLightbox(1);
        });

        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if ($('.hhmgt-lightbox').is(':visible')) {
                if (e.keyCode === 37) { // Left arrow
                    navigateLightbox(-1);
                } else if (e.keyCode === 39) { // Right arrow
                    navigateLightbox(1);
                } else if (e.keyCode === 27) { // Escape
                    closeLightbox();
                }
            }
        });

        // Photo upload handling
        $(document).on('change', '.hhmgt-photo-upload-input', function() {
            handlePhotoUpload(this);
        });

        // Remove photo preview
        $(document).on('click', '.hhmgt-photo-remove', function(e) {
            e.stopPropagation();
            $(this).closest('.hhmgt-photo-preview').remove();
        });
    }

    /**
     * Open lightbox
     */
    function openLightbox() {
        if (currentGallery.length === 0) {
            return;
        }

        const hasMultiple = currentGallery.length > 1;
        const prevBtn = hasMultiple ? '<button type="button" class="hhmgt-lightbox-nav hhmgt-lightbox-prev"><span class="material-symbols-outlined">chevron_left</span></button>' : '';
        const nextBtn = hasMultiple ? '<button type="button" class="hhmgt-lightbox-nav hhmgt-lightbox-next"><span class="material-symbols-outlined">chevron_right</span></button>' : '';

        const lightboxHTML = `
            <div class="hhmgt-lightbox">
                <div class="hhmgt-lightbox-overlay"></div>
                <button type="button" class="hhmgt-lightbox-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
                ${prevBtn}
                <img src="${currentGallery[currentIndex]}" class="hhmgt-lightbox-image" alt="Photo">
                ${nextBtn}
            </div>
        `;

        $('body').append(lightboxHTML);
        $('.hhmgt-lightbox').fadeIn(200);
    }

    /**
     * Close lightbox
     */
    function closeLightbox() {
        $('.hhmgt-lightbox').fadeOut(200, function() {
            $(this).remove();
        });
    }

    /**
     * Navigate lightbox
     */
    function navigateLightbox(direction) {
        currentIndex += direction;

        // Wrap around
        if (currentIndex < 0) {
            currentIndex = currentGallery.length - 1;
        } else if (currentIndex >= currentGallery.length) {
            currentIndex = 0;
        }

        // Update image
        $('.hhmgt-lightbox-image').fadeOut(100, function() {
            $(this).attr('src', currentGallery[currentIndex]).fadeIn(100);
        });
    }

    /**
     * Handle photo upload
     */
    function handlePhotoUpload(input) {
        const files = input.files;
        if (!files || files.length === 0) {
            return;
        }

        const $previewContainer = $(input).closest('.hhmgt-photo-upload').find('.hhmgt-photo-preview-list');
        if (!$previewContainer.length) {
            // Create preview container if it doesn't exist
            $(input).closest('.hhmgt-photo-upload').append('<div class="hhmgt-photo-preview-list"></div>');
        }

        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const $preview = $(`
                    <div class="hhmgt-photo-preview">
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="hhmgt-photo-remove">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                `);

                $previewContainer.append($preview);
            };

            reader.readAsDataURL(file);
        });

        // Clear input
        input.value = '';
    }

    /**
     * Upload photos to WordPress media library
     */
    function uploadPhotos(files, callback) {
        if (!files || files.length === 0) {
            callback([]);
            return;
        }

        const uploadedIds = [];
        let completed = 0;

        Array.from(files).forEach(function(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload-attachment');
            formData.append('_wpnonce', hhmgtData.nonce);

            $.ajax({
                url: hhmgtData.ajax_url.replace('admin-ajax.php', 'async-upload.php'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response && response.success && response.data && response.data.id) {
                        uploadedIds.push(response.data.id);
                    }
                },
                complete: function() {
                    completed++;
                    if (completed === files.length) {
                        callback(uploadedIds);
                    }
                }
            });
        });
    }

    /**
     * Render photo gallery from attachment IDs
     */
    function renderPhotoGallery(attachmentIds, $container) {
        if (!attachmentIds || attachmentIds.length === 0) {
            $container.html('<p style="color: #6b7280; font-size: 14px;">No photos</p>');
            return;
        }

        // In a real implementation, you would fetch the attachment URLs from WordPress
        // For now, we'll use placeholder logic
        $container.empty();

        attachmentIds.forEach(function(id) {
            const $photo = $(`
                <div class="hhmgt-photo-item">
                    <div class="hhmgt-photo-placeholder">
                        <span class="material-symbols-outlined">image</span>
                    </div>
                </div>
            `);

            $container.append($photo);
        });
    }

    /**
     * Get attachment URLs from IDs
     */
    function getAttachmentUrls(attachmentIds, callback) {
        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_attachment_urls',
                nonce: hhmgtData.nonce,
                attachment_ids: attachmentIds
            },
            success: function(response) {
                if (response.success && response.data.urls) {
                    callback(response.data.urls);
                } else {
                    callback([]);
                }
            },
            error: function() {
                callback([]);
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initPhotoGallery();
    });

    // Expose functions for use by other scripts
    window.hhmgtPhotoGallery = {
        uploadPhotos: uploadPhotos,
        renderPhotoGallery: renderPhotoGallery,
        getAttachmentUrls: getAttachmentUrls
    };

})(jQuery);
