/**
 * Live camera capture + GPS for attendance & shift closing (PRD ATT-1, ATT-2, ATT-6, SHF-3, NFR-3).
 *
 * - The photo MUST come from the live camera: getUserMedia first; when it is not available we fall back
 *   to <input type="file" accept="image/*" capture="user|environment"> which opens the camera on Android.
 * - Every photo is resized in the browser (max 1280 px, JPEG) before it is uploaded with $wire.upload().
 * - Location uses enableHighAccuracy; the distance check itself is done on the server.
 */

const MAX_SIZE = 1280;
const JPEG_QUALITY = 0.8;

/**
 * Draw a video frame / image onto a canvas no larger than MAX_SIZE and return a JPEG blob.
 */
function toJpeg(source, width, height, mirror = false) {
    const scale = Math.min(1, MAX_SIZE / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);

    const context = canvas.getContext('2d');

    if (mirror) {
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
    }

    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('encode'))), 'image/jpeg', JPEG_QUALITY);
    });
}

function cameraCapture({ field, facing = 'user' }) {
    return {
        field,
        facing,
        // idle | starting | live | uploading | preview | denied | fallback | error
        state: 'idle',
        previewUrl: null,
        message: '',
        stream: null,

        init() {
            if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
                this.state = 'fallback';

                return;
            }

            this.start();

            document.addEventListener('livewire:navigating', () => this.stop(), { once: true });
        },

        destroy() {
            this.stop();
        },

        async start() {
            this.state = 'starting';
            this.message = '';

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facing, width: { ideal: 1280 }, height: { ideal: 1280 } },
                    audio: false,
                });

                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.state = 'live';
            } catch (error) {
                this.stop();
                this.state = ['NotAllowedError', 'SecurityError', 'PermissionDeniedError'].includes(error?.name)
                    ? 'denied'
                    : 'fallback';
            }
        },

        stop() {
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },

        async capture() {
            const video = this.$refs.video;

            if (! video.videoWidth) {
                return;
            }

            try {
                const blob = await toJpeg(video, video.videoWidth, video.videoHeight, this.facing === 'user');
                this.stop();
                this.upload(blob);
            } catch {
                this.fail('Foto gagal diambil. Ketuk "Ulangi" lalu coba lagi.');
            }
        },

        /**
         * Fallback: the camera app returns a file; compress it the same way before uploading.
         */
        async fromFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (! file) {
                return;
            }

            if (! file.type.startsWith('image/')) {
                this.fail('File harus berupa foto.');

                return;
            }

            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                const blob = await toJpeg(bitmap, bitmap.width, bitmap.height);
                bitmap.close?.();
                this.upload(blob);
            } catch {
                this.fail('Foto tidak bisa dibaca. Ambil foto lagi.');
            }
        },

        upload(blob) {
            this.previewUrl && URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = URL.createObjectURL(blob);
            this.state = 'uploading';

            const file = new File([blob], 'foto.jpg', { type: 'image/jpeg' });

            this.$wire.upload(
                this.field,
                file,
                () => (this.state = 'preview'),
                () => this.fail('Foto gagal dikirim. Periksa internet, lalu ketuk "Ulangi".'),
            );
        },

        retake() {
            this.$wire.$set(this.field, null);
            this.previewUrl && URL.revokeObjectURL(this.previewUrl);
            this.previewUrl = null;

            this.stream === null && window.isSecureContext && navigator.mediaDevices?.getUserMedia
                ? this.start()
                : (this.state = 'fallback');
        },

        fail(message) {
            this.message = message;
            this.state = 'error';
        },
    };
}

function geoLocator() {
    return {
        // idle | locating | ready | denied | unavailable | insecure
        state: 'idle',
        lat: null,
        lng: null,
        accuracy: null,

        init() {
            this.locate();
        },

        get ready() {
            return this.state === 'ready';
        },

        locate() {
            if (! window.isSecureContext) {
                this.state = 'insecure';

                return;
            }

            if (! navigator.geolocation) {
                this.state = 'unavailable';

                return;
            }

            this.state = 'locating';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.lat = position.coords.latitude;
                    this.lng = position.coords.longitude;
                    this.accuracy = Math.round(position.coords.accuracy * 10) / 10;
                    this.state = 'ready';
                },
                (error) => {
                    this.state = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
            );
        },
    };
}

function register() {
    window.Alpine.data('cameraCapture', cameraCapture);
    window.Alpine.data('geoLocator', geoLocator);
}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
