const checkoutForm = document.getElementById('checkoutForm');
const transferFields = document.getElementById('transferFields');

document.querySelectorAll('input[name="payment_method"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        if (transferFields) transferFields.classList.toggle('d-none', radio.value !== 'transfer');
    });
});

if (checkoutForm) {
    const storeLatitude = Number(checkoutForm.dataset.storeLat);
    const storeLongitude = Number(checkoutForm.dataset.storeLng);
    const radiusKm = Number(checkoutForm.dataset.radiusKm || 10);
    const latitudeInput = document.getElementById('deliveryLatitude');
    const longitudeInput = document.getElementById('deliveryLongitude');
    const checkoutButton = document.getElementById('checkoutButton');
    const locationStatus = document.getElementById('locationStatus');
    const locationButton = document.getElementById('useCurrentLocation');
    const mapElement = document.getElementById('deliveryMap');
    const fulfillmentInput = document.getElementById('fulfillmentMethod');
    const codPayment = document.getElementById('codPayment');
    const transferPayment = document.getElementById('transferPayment');
    const codTitle = document.getElementById('codTitle');
    const codDescription = document.getElementById('codDescription');
    const transferDescription = document.getElementById('transferDescription');
    let destinationMarker = null;
    let hasSelectedLocation = false;

    const distanceKm = (lat1, lng1, lat2, lng2) => {
        const toRadians = (value) => value * Math.PI / 180;
        const earthRadius = 6371.0088;
        const latitudeDelta = toRadians(lat2 - lat1);
        const longitudeDelta = toRadians(lng2 - lng1);
        const a = Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(longitudeDelta / 2) ** 2;
        return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    if (mapElement && window.L && Number.isFinite(storeLatitude) && Number.isFinite(storeLongitude)) {
        const map = L.map(mapElement).setView([storeLatitude, storeLongitude], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
        L.marker([storeLatitude, storeLongitude]).addTo(map).bindPopup('Lokasi toko');
        L.circle([storeLatitude, storeLongitude], {radius: radiusKm * 1000, color: '#047857', fillColor: '#10b981', fillOpacity: 0.1}).addTo(map);

        const updateDeliveryPoint = (latitude, longitude, centerMap = false) => {
            latitudeInput.value = latitude.toFixed(7);
            longitudeInput.value = longitude.toFixed(7);
            const distance = distanceKm(storeLatitude, storeLongitude, latitude, longitude);
            const isDeliveryArea = distance <= radiusKm;
            hasSelectedLocation = true;
            fulfillmentInput.value = isDeliveryArea ? 'delivery' : 'pickup';
            if (!destinationMarker) {
                destinationMarker = L.marker([latitude, longitude], {draggable: true}).addTo(map);
                destinationMarker.bindPopup('Titik pengantaran').openPopup();
                destinationMarker.on('dragend', (event) => {
                    const point = event.target.getLatLng();
                    updateDeliveryPoint(point.lat, point.lng);
                });
            } else {
                destinationMarker.setLatLng([latitude, longitude]);
            }
            if (centerMap) map.setView([latitude, longitude], 16);
            if (isDeliveryArea) {
                locationStatus.className = 'location-status mt-3 bg-success-subtle text-success-emphasis';
                locationStatus.innerHTML = `<i class="bi bi-truck me-1"></i>Jarak sekitar <strong>${distance.toFixed(2)} km</strong>. Tersedia COD antar dan transfer antar.`;
                codTitle.innerHTML = '<i class="bi bi-cash-stack me-1"></i> COD Antar';
                codDescription.textContent = 'Bayar saat pesanan diantar ke alamat.';
                transferPayment.disabled = transferPayment.dataset.bankReady !== '1';
                transferDescription.textContent = transferPayment.dataset.bankReady === '1'
                    ? 'Transfer manual, lalu pesanan diantar setelah diverifikasi.'
                    : 'Belum ada rekening transfer aktif.';
            } else {
                locationStatus.className = 'location-status mt-3 bg-warning-subtle text-warning-emphasis';
                locationStatus.innerHTML = `<i class="bi bi-shop me-1"></i>Jarak sekitar <strong>${distance.toFixed(2)} km</strong>. Di luar radius 10 km, pesanan hanya dapat diambil dan dibayar di toko.`;
                codPayment.checked = true;
                codTitle.innerHTML = '<i class="bi bi-shop me-1"></i> COD di Toko';
                codDescription.textContent = 'Ambil pesanan dan bayar langsung di toko.';
                transferPayment.disabled = true;
                transferFields?.classList.add('d-none');
                transferDescription.textContent = 'Transfer tidak tersedia di luar radius 10 km.';
            }
            checkoutButton.disabled = false;
        };

        map.on('click', (event) => updateDeliveryPoint(event.latlng.lat, event.latlng.lng));
        const savedLatitude = Number(latitudeInput.value);
        const savedLongitude = Number(longitudeInput.value);
        if (latitudeInput.value !== '' && longitudeInput.value !== '' && Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude)) {
            updateDeliveryPoint(savedLatitude, savedLongitude, true);
        } else {
            checkoutButton.disabled = true;
        }

        locationButton?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                locationStatus.className = 'location-status mt-3 bg-warning-subtle text-warning-emphasis';
                locationStatus.textContent = 'Browser ini tidak mendukung pembacaan lokasi. Pilih titik pengantaran langsung pada peta.';
                return;
            }
            locationButton.disabled = true;
            locationButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mencari lokasi...';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    updateDeliveryPoint(position.coords.latitude, position.coords.longitude, true);
                    locationButton.disabled = false;
                    locationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i>Gunakan lokasi saya';
                },
                () => {
                    locationStatus.className = 'location-status mt-3 bg-warning-subtle text-warning-emphasis';
                    locationStatus.textContent = 'Lokasi tidak dapat dibaca. Izinkan akses lokasi atau pilih titik langsung pada peta.';
                    locationButton.disabled = false;
                    locationButton.innerHTML = '<i class="bi bi-crosshair me-1"></i>Gunakan lokasi saya';
                },
                {enableHighAccuracy: true, timeout: 15000, maximumAge: 30000}
            );
        });
    }

    checkoutForm.addEventListener('submit', (event) => {
        if (!hasSelectedLocation) {
            event.preventDefault();
            locationStatus?.scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
        checkoutButton.disabled = true;
        checkoutButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
    });
}
