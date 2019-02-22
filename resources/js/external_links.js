var external_links = {};
//NCBI
external_links['NCBI_EUTILS'] = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi';
external_links['NCBI_SNP'] = 'http://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?rs=<QUERY>'
external_links['NCBI_VARIATION_VIEWER'] = 'https://www.ncbi.nlm.nih.gov/variation/view/?q=<QUERY>&filters=source:dbsnp&assm=GCF_000001405.28'
external_links['NCBI_NUCLEOTIDE'] = 'http://www.ncbi.nlm.nih.gov/nuccore/<QUERY>';
external_links['NCBI_CLINVAR'] = 'http://www.ncbi.nlm.nih.gov/clinvar/<QUERY>';
//ENSEMBLE
external_links['ENSEMBL_ID'] = 'http://www.ensembl.org/id/<QUERY>';
external_links['ENSEMBL_GENE'] = 'http://www.ensembl.org/Homo_sapiens/Gene/Summary?g=<QUERY>';
external_links['ENSEMBL_VARIATION'] = 'http://www.ensembl.org/Homo_sapiens/Variation/Explore?v=<QUERY>&filters=source:dbsnp&assm=GCF_000001405.28';
external_links['ENSEMBL_REGULATION'] = 'http://grch37.ensembl.org/Homo_sapiens/Regulation/Context?rf=<QUERY>';
//UNIPROT
external_links['UNIPROT_UNIPROT'] = 'http://www.uniprot.org/uniprot/<QUERY>';
external_links['UNIPROT_UNIPRAC'] = 'http://www.uniprot.org/uniparc/<QUERY>';
//HARMONIZOME
external_links['HARMONIZOME_PROTEIN'] = 'http://amp.pharm.mssm.edu/Harmonizome/protein/<QUERY>';
//EMBL-EBI
external_links['EMBL-EBI_SEQUENCE'] = 'http://www.ebi.ac.uk/ena/data/view/<QUERY>';

var ExternalLinks = {
    NCBI:{
        Eutils: function(id){ return external_links['NCBI_EUTILS'].replace('<QUERY>', id); },
        SNP: function(rsid){ return external_links['NCBI_SNP'].replace('<QUERY>', rsid); },
        VariationViewer: function(rsid){ return external_links['NCBI_VARIATION_VIEWER'].replace('<QUERY>', rsid); },
        Nucleotide: function(id){ return external_links['NCBI_NUCLEOTIDE'].replace('<QUERY>', id); },
        Clinvar: function(accession){ return external_links['NCBI_CLINVAR'].replace('<QUERY>', accession); }
    },
    ENSEMBL: {
        Id: function(id){ return external_links['ENSEMBL_ID'].replace('<QUERY>', id); },
        Gene: function(symbol){ return external_links['ENSEMBL_GENE'].replace('<QUERY>', symbol); },
        Variation: function(id){ return external_links['ENSEMBL_VARIATION'].replace('<QUERY>', id); },
        Regulation: function(rsid){ return external_links['ENSEMBL_REGULATION'].replace('<QUERY>', rsid); }
    },
    EMBL_EBI : {
        Sequence: function(seq){ return external_links['EMBL-EBI_SEQUENCE'].replace('<QUERY>', seq); }
    },
    UNIPROT:{
        Uniprot: function(id){ return external_links['UNIPROT_UNIPROT'].replace('<QUERY>', id); },
        Uniparc: function(id){ return external_links['UNIPROT_UNIPRAC'].replace('<QUERY>', id); }
    },
    Harmonizome:{
        Protein: function(id){ return external_links['HARMONIZOME_PROTEIN'].replace('<QUERY>', id); },
    }
};