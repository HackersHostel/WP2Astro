<?php
// wp eval-file dev/seed.php [lighthouse id] [mill id] [crypt id] — the three images imported first (default 4 5 6).
$m = isset($args) && 3 === count($args) ? array_map('intval', $args) : [4, 5, 6];
$img = fn($id,$size='large') => wp_get_attachment_image_url($m[$id - 4],$size);
$c1 = wp_insert_term('Haunted Houses','category')['term_id'];
$c2 = wp_insert_term('Local Legends','category')['term_id'];
$posts = [
 ['The Lighthouse Keeper Who Never Left', '2009-10-31 22:13:00', [$c1], ['lighthouse','ghosts'],
  "<p>Every October the lamp turns on by itself. Nobody has climbed the <strong>143 steps</strong> since 1987.</p>\n<p><img class=\"aligncenter size-large wp-image-".$m[0]."\" src=\"".$img(4)."\" alt=\"The lighthouse at dusk\" width=\"800\" height=\"450\" /></p>\n<h2>What the logbook says</h2>\n<blockquote><p>Light lit at 18:04. Fog thick. Someone knocked three times.</p></blockquote>\n<ul>\n<li>First sighting: 1952</li>\n<li>Last sighting: <em>last night</em>\n<ul><li>by two fishermen</li></ul></li>\n</ul>\n<p>Read more in <a href=\"".home_url('/about/')."\">About this paper</a>.</p>", 4],
 ['Ten Things Found in the Old Mill', '2010-03-14 09:00:00', [$c1,$c2], ['mill'],
  "<!-- wp:paragraph --><p>We counted them twice. The count changed.</p><!-- /wp:paragraph -->\n<!-- wp:list {\"ordered\":true} --><ol><li>A pocket watch stopped at 3:17</li><li>A child's shoe</li><li>Flour that never spoils</li></ol><!-- /wp:list -->\n<!-- wp:table --><figure class=\"wp-block-table\"><table><thead><tr><th>Item</th><th>Floor</th></tr></thead><tbody><tr><td>Watch</td><td>2</td></tr></tbody></table></figure><!-- /wp:table -->\n<!-- wp:code --><pre class=\"wp-block-code\"><code>if (door.isOpen()) { run(); }</code></pre><!-- /wp:code -->", 5],
 ['Visual Composer Was Here', '2011-07-07 12:00:00', [$c2], ['plugins'],
  "[vc_row][vc_column width=\"1/2\"]<p>This post was built with a page builder that has been dead for years.</p>[/vc_column][vc_column width=\"1/2\"][button url=\"https://example.com/tour\" color=\"red\"]Book a ghost tour[/button][/vc_column][/vc_row]\n<p>[contact-form-7 id=\"12\" title=\"Contact\"]</p>", 0],
 ['A Video From the Crypt', '2012-11-02 23:59:00', [$c2], ['video'],
  "<p>Filmed on Día de los Muertos.</p>\n<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/dQw4w9WgXcQ\" title=\"Crypt\" allowfullscreen></iframe>\n<p><img src=\"".$img(6,'medium')."\" alt=\"crypt\" /></p>", 6],
];
foreach ($posts as [$t,$d,$cats,$tags,$content,$thumb]) {
  $id = wp_insert_post(['post_title'=>$t,'post_date'=>$d,'post_status'=>'publish','post_content'=>$content,'post_category'=>$cats,'tags_input'=>$tags]);
  if ($thumb) set_post_thumbnail($id,$m[$thumb - 4]);
}
for ($i=1;$i<=10;$i++) wp_insert_post(['post_title'=>"Night report #$i",'post_date'=>sprintf('2013-01-%02d 21:00:00',$i),'post_status'=>'publish','post_content'=>"<p>Quiet night number $i. Mostly.</p>"]);
$about = wp_insert_post(['post_title'=>'About','post_name'=>'about','post_type'=>'page','post_status'=>'publish','post_content'=>'<p>The Ghost Town Gazette has reported on the departed since 2009.</p>']);
wp_insert_post(['post_title'=>'Our Team','post_name'=>'team','post_parent'=>$about,'post_type'=>'page','post_status'=>'publish','post_content'=>'<p>Editors, living and otherwise.</p>']);
$menu = wp_create_nav_menu('Main');
wp_update_nav_menu_item($menu,0,['menu-item-title'=>'Home','menu-item-url'=>home_url('/'),'menu-item-status'=>'publish']);
wp_update_nav_menu_item($menu,0,['menu-item-title'=>'About','menu-item-object'=>'page','menu-item-object-id'=>$about,'menu-item-type'=>'post_type','menu-item-status'=>'publish']);
wp_update_nav_menu_item($menu,0,['menu-item-title'=>'Haunted Houses','menu-item-object'=>'category','menu-item-object-id'=>$c1,'menu-item-type'=>'taxonomy','menu-item-status'=>'publish']);
set_theme_mod('nav_menu_locations',['primary'=>$menu]);
echo "seeded\n";
