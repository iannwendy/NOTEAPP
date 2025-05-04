#!/bin/bash
# Script để tạo các biểu tượng PWA từ một hình ảnh 512x512

# Đường dẫn đến thư mục biểu tượng
ICON_DIR="public/icons"

# Tạo các biểu tượng cho PWA
# Các kích thước cần thiết: 72, 96, 128, 144, 152, 192, 384, 512

echo "Đang tạo các biểu tượng PWA..."

# Tạo biểu tượng 72x72
echo "Tạo icon-72x72.png"
convert -resize 72x72 source-icon.png $ICON_DIR/icon-72x72.png

# Tạo biểu tượng 96x96
echo "Tạo icon-96x96.png"
convert -resize 96x96 source-icon.png $ICON_DIR/icon-96x96.png

# Tạo biểu tượng 128x128
echo "Tạo icon-128x128.png"
convert -resize 128x128 source-icon.png $ICON_DIR/icon-128x128.png

# Tạo biểu tượng 144x144
echo "Tạo icon-144x144.png"
convert -resize 144x144 source-icon.png $ICON_DIR/icon-144x144.png

# Tạo biểu tượng 152x152
echo "Tạo icon-152x152.png"
convert -resize 152x152 source-icon.png $ICON_DIR/icon-152x152.png

# Tạo biểu tượng 192x192
echo "Tạo icon-192x192.png"
convert -resize 192x192 source-icon.png $ICON_DIR/icon-192x192.png

# Tạo biểu tượng 384x384
echo "Tạo icon-384x384.png"
convert -resize 384x384 source-icon.png $ICON_DIR/icon-384x384.png

# Tạo biểu tượng 512x512
echo "Tạo icon-512x512.png"
convert -resize 512x512 source-icon.png $ICON_DIR/icon-512x512.png

echo "Tạo xong các biểu tượng!"
echo "Các biểu tượng được lưu trong: $ICON_DIR" 