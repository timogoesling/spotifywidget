<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Ubuntu:400,700">
    <style>
        body {
            font-family: 'Ubuntu', sans-serif;
            margin: 0;
            min-height: 100vh;
            width: 100%;
            display: flex;
        }

        .container {
    display: flex;
    width: 100%;
    height: 100vh;
    position: absolute; /* Set position to absolute */
}

.content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 100%; /* Set width to 100% */
    top: 0;
    left: 0;
}

.widget-container {
    background-color: #000;
    color: #fff;
    border-radius: 10px;
    font-family: 'Ubuntu', sans-serif;
    display: flex;
    background-position: center center;
    background-repeat: no-repeat;
    background-size: cover;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    height: 100%;
    width: 100%; /* Set width to 100% */
    text-align: center;
    position: fixed;
    bottom: 0;
    left: 0;
}

        .album-cover {
            border-radius: 5px;
            object-fit: cover;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            margin-bottom: 5px;
            display: none;
        }

        .song-name,
        .artist {
            color: #fff;
            text-align: center;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 1);
            overflow: hidden;
            text-overflow: ellipsis;
            width: calc(100% - 20px);
            margin-bottom: 5px;
        }

        .song-name,
        #paused {
            font-size: 24px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
        }

        .artist {
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
        }

        .progress-bar {
            width: 80%;
            height: 3%;
            background-color: #555;
            border-radius: 20px;
            position: relative;
            margin-top: 20%;
            margin-bottom: 5px;
        }

        .progress {
            height: 100%;
            border-radius: 20px;
            background-color: #1DB954;
            position: absolute;
            animation: progressAnimation 500ms linear forwards;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">        
            <div class="widget-container" id="nowPlaying">
                <div class="background-image"></div>
                <img class="album-cover" alt="Album Cover">
                <div class="song-name"></div>
                <div class="artist"></div>
                <div class="progress-bar">
                    <div class="progress"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const clientId = 'YOUR_CLIENT_ID';
        const clientSecret = 'YOUR_CLIENT_SECRET';
        const redirectUri = 'YOUR_REDIRECT_URI';
        const nowPlayingEndpoint = 'https://api.spotify.com/v1/me/player/currently-playing';

        let accessToken;

        const authenticateSpotify = () => {
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            accessToken = sessionStorage.getItem('access_token');

            if (code) {
                if (!accessToken) {
                    getAccessToken(code);
                } else {
                    updateNowPlaying(accessToken);
                    startTokenRefreshInterval();
                }
            } else if (accessToken) {
                checkTokenValidity(accessToken);
                startTokenRefreshInterval();
            } else {
                redirectToSpotifyLogin();
            }

            startTokenRefreshInterval();
        };

        const checkTokenValidity = (token) => {
            const tokenExpiration = sessionStorage.getItem('token_expiration');
            if (tokenExpiration && new Date(tokenExpiration) > new Date()) {
                updateNowPlaying(token);
            } else {
                refreshToken();
            }
        };

        const startTokenRefreshInterval = () => {
            setInterval(() => {
                const refreshToken = sessionStorage.getItem('refresh_token');
                if (refreshToken) {
                    renewAccessToken(refreshToken);
                }
            }, 500);
        };

        const renewAccessToken = async (refreshToken) => {
            try {
                const response = await fetch('https://accounts.spotify.com/api/token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Authorization': 'Basic ' + btoa(clientId + ':' + clientSecret),
                    },
                    body: `grant_type=refresh_token&refresh_token=${refreshToken}`,
                });

                if (!response.ok) {
                    throw new Error('Fehler beim Auffrischen des Zugriffstokens');
                }

                const data = await response.json();
                accessToken = data.access_token;
                sessionStorage.setItem('access_token', accessToken);
                const expirationTime = new Date(new Date().getTime() + data.expires_in * 1000);
                sessionStorage.setItem('token_expiration', expirationTime);

                updateNowPlaying(accessToken);
            } catch (error) {
                console.error('Fehler beim Auffrischen des Zugriffstokens:', error);
            }
        };

        const redirectToSpotifyLogin = () => {
            const authUrl = `https://accounts.spotify.com/authorize?client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUri)}&scope=user-read-currently-playing&response_type=code`;
            window.location.href = authUrl;
        };

        const getAccessToken = async (code) => {
            try {
                const response = await fetch('https://accounts.spotify.com/api/token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Authorization': 'Basic ' + btoa(clientId + ':' + clientSecret),
                    },
                    body: `grant_type=authorization_code&code=${code}&redirect_uri=${encodeURIComponent(redirectUri)}`,
                });

                if (!response.ok) {
                    throw new Error('Fehler beim Abrufen des Zugriffstokens');
                }

                const data = await response.json();
                accessToken = data.access_token;
                sessionStorage.setItem('access_token', accessToken);
                sessionStorage.setItem('refresh_token', data.refresh_token);
                const expirationTime = new Date(new Date().getTime() + data.expires_in * 1000);
                sessionStorage.setItem('token_expiration', expirationTime);

                updateNowPlaying(accessToken);
            } catch (error) {
                console.error('Fehler beim Abrufen des Zugriffstokens:', error);
            }
        };

        const updateNowPlaying = async (accessToken) => {
            try {
                const response = await fetch(nowPlayingEndpoint, {
                    headers: {
                        'Authorization': 'Bearer ' + accessToken,
                    },
                });

                if (!response.ok) {
                    throw new Error('Fehler beim Abrufen des aktuellen Tracks');
                }

                const data = await response.json();
                const widgetContainer = document.getElementById('nowPlaying');
                const progressBar = widgetContainer.querySelector('.progress');

                if (data.item) {
                    const songName = data.item.name.length > 18 ? data.item.name.substring(0, 18) + '...' : data.item.name;
                    const artistName = data.item.artists[0].name;
                    const albumImage = data.item.album.images[0].url;
                    const durationMs = data.item.duration_ms;
                    const progressMs = data.progress_ms;

                    widgetContainer.style.backgroundImage = `url('${albumImage}')`;

                    widgetContainer.innerHTML = `
                        <img class="album-cover" src="${albumImage}" alt="Album Cover">
                        <div class="song-name">${songName}</div>
                        <div class="artist">${artistName}</div>
                        <div class="progress-bar">
                            <div class="progress" style="width: ${(progressMs / durationMs) * 100}%"></div>
                        </div>
                    `;

                    if (data.is_playing) {
                        widgetContainer.style.backgroundColor = '#000';
                        startProgressBarAnimation(progressBar, durationMs - progressMs);
                    } else {
                        widgetContainer.style.backgroundImage = 'none';
                        widgetContainer.style.backgroundColor = 'black';
                        widgetContainer.innerHTML = '<p id="paused">Paused</p>';
                    }
                } else {
                    widgetContainer.style.backgroundImage = 'none';
                    widgetContainer.style.backgroundColor = '#000';
                    widgetContainer.innerHTML = '';
                }
            } catch (error) {
                console.error('Fehler beim Aktualisieren des aktuellen Tracks:', error);
            }
        };

        const startProgressBarAnimation = (progressBar, duration) => {
            if (progressBar) {
                progressBar.style.animation = `progressAnimation ${duration}ms linear forwards`;
            }
        };

        window.onload = authenticateSpotify;    
</script>
</body>
</html>