Drop exported figure images in here, named exactly as each variant's
`suggested_filename` (see the `figs` array for any fixture in
data/fixtures_app_data.json), e.g.:

    sc-150-w-flood-light-datasheet-fig1.png
    sc-150-w-flood-light-datasheet-fig2.png

The existing `/app/<path:filename>` route in app.py already serves this
folder at `/app/images/<filename>` — no backend change needed. The
generator's Figures section tries to load `/app/images/<suggested_filename>`
for every figure and falls back to the dashed placeholder box automatically
if that file 404s, so you can drop images in incrementally.
