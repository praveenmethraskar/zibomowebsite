from pathlib import Path
import base64, re, urllib.request

out = Path('assets/images/clients')
out.mkdir(parents=True, exist_ok=True)

def download(name, urls):
    for url in urls:
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=30) as r:
                data = r.read()
            if len(data) >= 1000:
                (out / name).write_bytes(data)
                return
        except Exception:
            pass
    raise RuntimeError(f'Could not download {name}')

download('prasads.png', [
    'https://www.joonsquare.com/usermanage/image/business/prasads-multiplex-hyderabad-34123/prasads-multiplex-hyderabad-logo.png',
    'https://pbs.twimg.com/profile_images/1618498239136960513/4cz-Lbb9_400x400.jpg'
])
download('odeon-mall.png', ['https://odeonmall.com/wp-content/uploads/2025/04/odeon-mall-Logo-White.png'])
download('swarnagiri.png', [
    'https://ytdtemple.com/uploads/pooja/84e553725fbb19675c7d4e4bc42f90f3.png',
    'https://ytdtemple.com/front_assets/images/swarngiri_logo.png'
])
download('t-hub.png', [
    'https://t-hub.co/wp-content/uploads/2019/07/thub-logo-3.png',
    'https://t-hub.co/static/relaunch/assets/img/t_hub_logo.png'
])

upzy = '''/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAkGBwgHBgkIBwgKCgkLDRYPDQwMDRsUFRAWIB0iIiAdHx8