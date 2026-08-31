export function isImageFile(file) {
    const mime = (file?.type ?? "").toLowerCase();

    return mime.startsWith("image/");
}
