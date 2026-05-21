"""Generate WordPress.org banner assets from the CitationRate logo source.

Outputs:
  banner-772x250.png    (standard banner)
  banner-1544x500.png   (retina banner)

Layout: solid sage-green background matching the logo, logo placed on the
right side, white "Citability Score" headline + subtitle on the left.
"""

import os
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(__file__)
LOGO_PATH = os.path.join(HERE, "icon-source.png")

# Sage green matching the source logo background (sampled at pixel (10,10)).
src = Image.open(LOGO_PATH).convert("RGB")
BG = src.getpixel((10, 10))


def find_font(size: int) -> ImageFont.FreeTypeFont:
    candidates = [
        "/System/Library/Fonts/HelveticaNeue.ttc",
        "/System/Library/Fonts/Helvetica.ttc",
        "/Library/Fonts/Arial.ttf",
        "/System/Library/Fonts/Supplemental/Arial.ttf",
    ]
    for path in candidates:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                pass
    return ImageFont.load_default()


def draw_banner(width: int, height: int, output: str) -> None:
    img = Image.new("RGB", (width, height), BG)
    draw = ImageDraw.Draw(img)

    # Logo on the right, vertically centered.
    logo_size = int(height * 0.7)
    logo = src.resize((logo_size, logo_size), Image.LANCZOS)
    logo_x = width - logo_size - int(width * 0.06)
    logo_y = (height - logo_size) // 2
    img.paste(logo, (logo_x, logo_y))

    title_size = int(height * 0.22)
    subtitle_size = int(height * 0.09)
    tag_size = int(height * 0.07)

    title_font = find_font(title_size)
    subtitle_font = find_font(subtitle_size)
    tag_font = find_font(tag_size)

    pad = int(width * 0.05)
    text_y = int(height * 0.30)

    draw.text((pad, text_y), "Citability Score", fill=(255, 255, 255), font=title_font)
    text_y += int(title_size * 1.15)
    draw.text((pad, text_y), "Ottimizza i contenuti per gli AI search engine", fill=(245, 245, 245), font=subtitle_font)
    text_y += int(subtitle_size * 1.5)
    draw.text((pad, text_y), "by CitationRate", fill=(220, 230, 225), font=tag_font)

    img.save(output, "PNG", optimize=True)
    print(f"wrote {output}  ({width}x{height})")


draw_banner(772, 250, os.path.join(HERE, "banner-772x250.png"))
draw_banner(1544, 500, os.path.join(HERE, "banner-1544x500.png"))
