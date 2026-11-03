<?php
/** Editor-insertable directory layout preview. No live data or actions. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BH_Directory_Preview_3 {
    public static function register() {
        add_shortcode( 'bubbahub_directory_preview', array( __CLASS__, 'render' ) );
    }
    public static function render() {
        $items = array(
            array( 'Baby & toddler groups', 'Explore local groups and meet other families.', 'Parent & Baby' ),
            array( 'Swimming & movement', 'Discover active sessions for little ones.', 'Activities' ),
            array( 'Family days out', 'Find places to explore together.', 'Days Out' ),
        );
        ob_start();
        ?>
        <section class="bh3-directory-preview" aria-label="Bubba Hub directory design preview">
          <style>
            .bh3-directory-preview{font-family:inherit;color:#173c34;max-width:1200px;margin:2rem auto;padding:clamp(1rem,3vw,2.5rem)}
            .bh3-directory-preview *{box-sizing:border-box}
            .bh3-directory-preview .bh3-hero{background:#e7f3eb;border-radius:22px;padding:clamp(1.5rem,4vw,3.5rem)}
            .bh3-directory-preview h2{font-size:clamp(1.8rem,3vw,2.8rem);line-height:1.2;margin:0 0 1rem}
            .bh3-directory-preview p{line-height:1.6}
            .bh3-directory-preview .bh3-filters{display:flex;gap:.7rem;flex-wrap:wrap;margin:1.5rem 0}
            .bh3-directory-preview .bh3-filter{border:1px solid #c8d7cf;border-radius:12px;padding:.75rem 1rem;background:#fff;color:#34594b}
            .bh3-directory-preview .bh3-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
            .bh3-directory-preview .bh3-card{border:1px solid #d9e4dd;border-radius:16px;overflow:hidden;background:white}
            .bh3-directory-preview .bh3-placeholder{background:linear-gradient(135deg,#d7eadc,#f7e9d8);height:150px}
            .bh3-directory-preview .bh3-card-body{padding:1.1rem}
            .bh3-directory-preview .bh3-tag{font-size:.8rem;color:#466e5c;font-weight:700}
            @media(max-width:760px){.bh3-directory-preview .bh3-grid{grid-template-columns:1fr}}
          </style>
          <div class="bh3-hero">
            <p class="bh3-tag">DEVON &amp; CORNWALL</p>
            <h2>Discover activities for your family</h2>
            <p>Find baby groups, toddler classes and family days out near you.</p>
          </div>
          <p><strong>Design preview only.</strong> Filters, map and cards are placeholders, not working searches or real listings.</p>
          <div class="bh3-filters" aria-label="Future directory filters">
            <span class="bh3-filter">Keyword search</span><span class="bh3-filter">Category</span><span class="bh3-filter">Age</span><span class="bh3-filter">Day</span><span class="bh3-filter">Location</span><span class="bh3-filter">Free only</span>
          </div>
          <div class="bh3-grid">
          <?php foreach ( $items as $item ) : ?>
            <article class="bh3-card">
              <div class="bh3-placeholder" aria-hidden="true"></div>
              <div class="bh3-card-body"><span class="bh3-tag"><?php echo esc_html( $item[2] ); ?></span><h3><?php echo esc_html( $item[0] ); ?></h3><p><?php echo esc_html( $item[1] ); ?></p></div>
            </article>
          <?php endforeach; ?>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
