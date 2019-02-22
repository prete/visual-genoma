Genoverse.Track.VisualGenoma = Genoverse.Track.extend({
  id     : 'visualgenoma',
  name   : 'Sus Variantes',
  height : 100,
  legend : true,
  url: '/variants/track?chr=__CHR__&start=__START__&end=__END__',
  impacts: [
    {label: 'HIGH',       color: '#ff0000'},
    {label: 'MODERATE',   color: '#ffff00'},
    {label: 'LOW',        color: '#00ff00'},
    {label: 'MODIFIER',   color: '#0000ff'}
  ],
  constructor: function () {
    this.base.apply(this, arguments);

    if (this.legend === true) {
      this.addLegend();
    }
  },

  insertFeature: function (feature) {
    this.prop('impacts').forEach(function(impact){
      if(feature.impact.includes(impact.label)){
        feature.color = impact.color;
        feature.style = 'stroke: black; stroke-width: 1.8';
        return false;
      }
    });

    if (feature.start > feature.end) {
      feature.decorations = true;
    }

    this.base(feature);
  },

  populateMenu: function (feature) {
    
    var url  = '/variant/id/' + (feature.id);
    var menu = {
      title    : '<a target="_blank" href="' + url + '">' + (feature.variant_type) + (feature.rsid ? ' (' + feature.rsid + ')' : '') + '</a>',
      'Cromosoma' : this.browser.chr,
      'Posicion' : feature.start + '-' + feature.end,
      'Impacto' : feature.impact,
      'Alelo' : feature.ref_allele,
      'Alteración' : feature.alt_allele,
      'Ver Variante' : '<a target="_blank" href="/variants/id/' + feature.id + '"><i class="fa fa-fw fa-search"></i></a>'
    };
    if(feature.rsid){
      menu.RSid = '<a target="_blank" href="https://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?searchType=adhoc_search&type=rs&rs=' + feature.rsid + '">' + feature.rsid + '<i class="fa fa-fw fa-external-link"></i></a>';
    }
    return menu;
  },

  // Different settings for different zoom level
  2000000: { // This one applies when > 2M base-pairs per screen
    labels : true
  },
  100000: { // more than 100K but less then 2M
    labels : true
    },
  1: { // > 1 base-pair, but less then 100K
    labels : true
    }
});