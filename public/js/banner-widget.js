(function () {
    const config = {
        apiUrl: 'http://localhost:8080/api/banner', // Replace with your API endpoint
        containerId: 'banner-container',
        style: `
            .banner-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                justify-content: center;
            }
            .banner-item {
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                transition: transform 0.3s ease;
                max-width: 300px;
            }
            .banner-item:hover {
                transform: scale(1.03);
            }
            .banner-image {
                width: 100%;
                height: auto;
                display: block;
            }
            .banner-title {
                padding: 10px;
                text-align: center;
                background: #f5f5f5;
                font-family: Arial, sans-serif;
                color: #333;
            }
        `
    };

    if (!document.getElementById(config.containerId)) {
        const container = document.createElement('div');
        container.id = config.containerId;
        container.className = 'banner-container';
        document.body.appendChild(container);
    }

    const styleElement = document.createElement('style');
    styleElement.textContent = config.style;
    document.head.appendChild(styleElement);

    async function loadBanners() {
        try {
            const response = await fetch(config.apiUrl);
            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                const container = document.getElementById(config.containerId);

                const activeBanners = data.data
                    .filter(banner => banner.is_active === "1")
                    .sort((a, b) => parseInt(a.order) - parseInt(b.order));

                activeBanners.forEach(banner => {
                    const bannerElement = document.createElement('div');
                    bannerElement.className = 'banner-item';

                    let content;
                    if (banner.link) {
                        content = document.createElement('a');
                        content.href = banner.link;
                        content.target = '_blank';
                        content.rel = 'noopener noreferrer';
                    } else {
                        content = document.createElement('div');
                    }

                    if (banner.image) {
                        const img = document.createElement('img');
                        img.src = banner.image;
                        img.alt = banner.title;
                        img.className = 'banner-image';
                        content.appendChild(img);
                    } else if (banner.link) {
                        const img = document.createElement('img');
                        img.src = banner.link;
                        img.alt = banner.title;
                        img.className = 'banner-image';
                        content.appendChild(img);
                    }

                    const title = document.createElement('div');
                    title.className = 'banner-title';
                    title.textContent = banner.title;
                    content.appendChild(title);

                    bannerElement.appendChild(content);
                    container.appendChild(bannerElement);
                });
            }
        } catch (error) {
            console.error('Error loading banners:', error);
        }
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(loadBanners, 1);
    } else {
        document.addEventListener('DOMContentLoaded', loadBanners);
    }
})();