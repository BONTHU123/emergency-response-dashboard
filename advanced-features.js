// ============================================================
// EMERGENCY RESPONSE DASHBOARD - ADVANCED FEATURES
// ============================================================


// ============================================================
// 1. LIVE EMERGENCY MAP
// ============================================================

let emergencyMap = null;
let emergencyMarkers = [];


function initializeEmergencyMap() {

    const mapElement = document.getElementById("emergencyMap");

    if (!mapElement) {
        return;
    }

    // Prevent creating the map more than once
    if (emergencyMap) {
        emergencyMap.invalidateSize();
        return;
    }

    emergencyMap = L.map("emergencyMap").setView(
        [16.5062, 80.6480],
        10
    );

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(emergencyMap);

    loadEmergencyMapIncidents();
}


// Load incidents and show them on map
async function loadEmergencyMapIncidents() {

    if (!emergencyMap) {
        return;
    }

    try {

        const response =
            await fetch("incidents.php");

        if (!response.ok) {
            throw new Error("Could not load incidents");
        }

        const incidents =
            await response.json();

        // Remove old markers
        emergencyMarkers.forEach(marker => {
            emergencyMap.removeLayer(marker);
        });

        emergencyMarkers = [];

        for (const incident of incidents) {

            if (
                incident.latitude &&
                incident.longitude
            ) {

                const latitude =
                    parseFloat(incident.latitude);

                const longitude =
                    parseFloat(incident.longitude);

                if (
                    !isNaN(latitude) &&
                    !isNaN(longitude)
                ) {

                    let marker =
                        L.marker([
                            latitude,
                            longitude
                        ]).addTo(emergencyMap);

                    marker.bindPopup(`
                        <div class="map-popup">
                            <h3>🚨 ${escapeMapHTML(incident.title)}</h3>

                            <p>
                                <strong>Location:</strong>
                                ${escapeMapHTML(incident.location)}
                            </p>

                            <p>
                                <strong>Priority:</strong>
                                ${escapeMapHTML(incident.priority)}
                            </p>

                            <p>
                                <strong>Status:</strong>
                                ${escapeMapHTML(incident.status)}
                            </p>

                            <p>
                                <strong>Responder:</strong>
                                ${escapeMapHTML(
                                    incident.responder ||
                                    "Not Assigned"
                                )}
                            </p>
                        </div>
                    `);

                    emergencyMarkers.push(marker);
                }
            }
        }

    } catch (error) {

        console.error(
            "Emergency map error:",
            error
        );
    }
}


// Open map for a location
function openEmergencyMapLocation(
    latitude,
    longitude,
    title = "Emergency Location"
) {

    if (!emergencyMap) {
        initializeEmergencyMap();
    }

    setTimeout(() => {

        emergencyMap.setView(
            [latitude, longitude],
            16
        );

        L.marker([
            latitude,
            longitude
        ])
        .addTo(emergencyMap)
        .bindPopup(
            `<strong>📍 ${escapeMapHTML(title)}</strong>`
        )
        .openPopup();

    }, 300);
}


// Security helper
function escapeMapHTML(value) {

    const div =
        document.createElement("div");

    div.textContent =
        value ?? "";

    return div.innerHTML;
}


// ============================================================
// 2. EMERGENCY SOS / ALERT
// ============================================================

let sosSending = false;


async function sendEmergencySOS() {

    if (sosSending) {
        return;
    }

    const confirmed =
        confirm(
            "🚨 EMERGENCY SOS\n\n" +
            "Are you sure you want to send an emergency alert?"
        );

    if (!confirmed) {
        return;
    }

    sosSending = true;

    const button =
        document.getElementById("sosButton");

    if (button) {
        button.disabled = true;
        button.innerHTML =
            "⏳ Sending Alert...";
    }

    try {

        // Get current location
        if (!navigator.geolocation) {

            throw new Error(
                "Location service is not available."
            );
        }

        navigator.geolocation.getCurrentPosition(
            async function(position) {

                const latitude =
                    position.coords.latitude;

                const longitude =
                    position.coords.longitude;

                const alertData = {

                    alert_type: "SOS",

                    message:
                        "Emergency SOS alert received",

                    latitude:
                        latitude,

                    longitude:
                        longitude,

                    status:
                        "Active"
                };

                const response =
                    await fetch(
                        "sos.php",
                        {
                            method: "POST",

                            headers: {
                                "Content-Type":
                                    "application/json"
                            },

                            body:
                                JSON.stringify(
                                    alertData
                                )
                        }
                    );

                const result =
                    await response.json();

                if (result.success) {

                    alert(
                        "🚨 SOS SENT SUCCESSFULLY!\n\n" +
                        "Emergency team has been notified."
                    );

                    openEmergencyMapLocation(
                        latitude,
                        longitude,
                        "🚨 SOS Emergency"
                    );

                } else {

                    alert(
                        result.message ||
                        "Could not send SOS."
                    );
                }

                resetSOSButton();

            },

            function(error) {

                console.error(error);

                alert(
                    "Could not get your location.\n\n" +
                    "Please allow location access in your browser."
                );

                resetSOSButton();
            },

            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );

    } catch (error) {

        console.error(error);

        alert(
            "SOS error: " +
            error.message
        );

        resetSOSButton();
    }
}


function resetSOSButton() {

    sosSending = false;

    const button =
        document.getElementById("sosButton");

    if (button) {

        button.disabled = false;

        button.innerHTML =
            "🚨 SEND EMERGENCY SOS";
    }
}


// ============================================================
// 3. ANALYTICS & CHARTS
// ============================================================

let priorityChart = null;
let statusChart = null;


async function loadEmergencyAnalytics() {

    try {

        const response =
            await fetch(
                "analytics.php"
            );

        if (!response.ok) {
            throw new Error(
                "Analytics server error"
            );
        }

        const data =
            await response.json();

        updateAnalyticsCards(data);

        createPriorityChart(
            data.priority
        );

        createStatusChart(
            data.status
        );

    } catch (error) {

        console.error(
            "Analytics error:",
            error
        );
    }
}


// Update analytics cards
function updateAnalyticsCards(data) {

    const total =
        document.getElementById(
            "analyticsTotal"
        );

    const active =
        document.getElementById(
            "analyticsActive"
        );

    const resolved =
        document.getElementById(
            "analyticsResolved"
        );

    const high =
        document.getElementById(
            "analyticsHigh"
        );

    if (total) {
        total.textContent =
            data.total || 0;
    }

    if (active) {
        active.textContent =
            data.active || 0;
    }

    if (resolved) {
        resolved.textContent =
            data.resolved || 0;
    }

    if (high) {
        high.textContent =
            data.high_priority || 0;
    }
}


// Priority chart
function createPriorityChart(data) {

    const canvas =
        document.getElementById(
            "priorityChart"
        );

    if (!canvas) {
        return;
    }

    if (priorityChart) {
        priorityChart.destroy();
    }

    priorityChart =
        new Chart(
            canvas,
            {
                type: "doughnut",

                data: {

                    labels: [
                        "High",
                        "Medium",
                        "Low"
                    ],

                    datasets: [
                        {
                            data: [
                                data.high || 0,
                                data.medium || 0,
                                data.low || 0
                            ]
                        }
                    ]
                },

                options: {

                    responsive: true,

                    plugins: {
                        legend: {
                            position:
                                "bottom"
                        }
                    }
                }
            }
        );
}


// Status chart
function createStatusChart(data) {

    const canvas =
        document.getElementById(
            "statusChart"
        );

    if (!canvas) {
        return;
    }

    if (statusChart) {
        statusChart.destroy();
    }

    statusChart =
        new Chart(
            canvas,
            {
                type: "bar",

                data: {

                    labels: [
                        "Active",
                        "Resolved",
                        "Pending"
                    ],

                    datasets: [
                        {
                            label:
                                "Incidents",

                            data: [
                                data.active || 0,
                                data.resolved || 0,
                                data.pending || 0
                            ]
                        }
                    ]
                },

                options: {

                    responsive: true,

                    scales: {

                        y: {
                            beginAtZero:
                                true,

                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            }
        );
}


// ============================================================
// PAGE INITIALIZATION
// ============================================================

document.addEventListener(
    "DOMContentLoaded",
    function() {

        // Map
        initializeEmergencyMap();

        // Analytics
        loadEmergencyAnalytics();

    }
);