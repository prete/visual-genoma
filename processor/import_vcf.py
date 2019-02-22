#!/usr/bin/env python3
#**************************************
import sys
import os.path
from  os import walk
import json
import re
import time
import hashlib
import mysql.connector

class VCF(object):
	'''
	VCF (Variant Call Format) version 4.2
	http://samtools.github.io/hts-specs/VCFv4.2.pdf
	'''
	
	#Yankilevich annotations
	functional_annotations_fields = ['Allele','Annotation','Annotation_Impact','Gene_Name','Gene_ID','Feature_Type','Feature_ID','Transcript_BioType','Rank','HGVS_c','HGVS_p','cDNA','CDS','AA','Distance','ERRORS_WARNINGS_INFO']
	consequence_annotations_fields = ['Allele','Consequence','IMPACT','SYMBOL','Gene','Feature_type','Feature','BIOTYPE','EXON','INTRON','HGVSc','HGVSp','cDNA_position','CDS_position','Protein_position','Amino_acids','Codons','Existing_variation','DISTANCE','STRAND','SYMBOL_SOURCE','HGNC_ID','CANONICAL','TSL','CCDS','ENSP','SWISSPROT','TREMBL','UNIPARC','SIFT','PolyPhen','DOMAINS','GMAF','AFR_MAF','AMR_MAF','ASN_MAF','EAS_MAF','EUR_MAF','SAS_MAF','AA_MAF','EA_MAF','CLIN_SIG','SOMATIC','PUBMED','MOTIF_NAME','MOTIF_POS','HIGH_INF_POS','MOTIF_SCORE_CHANGE']
	clinvar_fields = ['CLINSIG','CLNDBN','CLNACC','CLNDSDB','CLNDSDBID','CLNREVSTAT']
	prediction_fields = ['Polyphen2_HDIV','Polyphen2_HVAR','PANTHER','PROVEAN','CAROL','MutationTaster','FATHMM','SIFT','CADD','Condel','SNAP']

	#Regular expressions
	r_meta_type = re.compile(r'##(.*?)=')
	r_meta_id = re.compile(r'ID=(.*?),')
	r_meta_desc1 =  re.compile(r'=(.*)')
	r_meta_desc2 =  re.compile(r'Description="(.*)"')
	r_frequency = re.compile(r'1000g2015aug_(afr|all|eas|eur)=(.*?)[;\n\r]+')
	r_refgenes = re.compile(r'(Func|Gene|GeneDetail|ExonicFunc|AAChange).refGene=(.*?)[;\n\r]+')
	r_functional = re.compile(r'ANN=(.*?)[;\n\r]+')
	r_consequences = re.compile(r'CSQ=(.*?)[;\n\r]+')
	r_clinvar = re.compile(r'(CLINSIG|CLNDBN|CLNACC|CLNDSDB|CLNDSDBID|CLNREVSTAT)=(.*?)[;\n\r]+')
	r_scores = re.compile(r'(Polyphen2_HDIV|Polyphen2_HVAR|PANTHER|PROVEAN|CAROL|MutationTaster|FATHMM|SIFT|CADD|Condel|SNAP)_score=(.*?)[;\n\r]+')
	r_predictions = re.compile(r'(PANTHER|CAROL|CADD|Condel|SNAP|(?:Polyphen2_HDIV|Polyphen2_HVAR|PROVEAN|MutationTaster|FATHMM|SIFT)_pred)=(.*?)[;\n\r]+')

	def show_progress(self):
		progress100 = (self.progress/self.file_size)*100.00
		timediff =  time.time()-self.start_time
		speed = self.progress/(1024.00*1024.00*timediff)
		remaining_time = (self.file_size-self.progress)/(self.progress/timediff)
		m, s = divmod(remaining_time, 60)
		remaining_time = '{0:02.00f}m {1:02.02f}s'.format(m, s)
		print('[+] Processed {0:.02f}% of {1:.02f}MB Remaining time: {2} [ ~{3:.02f}MB/s ] {4}'.format(progress100, self.file_size/(1024*1024), remaining_time, speed,' '*10), end='\r')

	def __init__(self, file, originalname):		
		self.mysql_config = {}
		self.start_time = time.time()
		self.end_time = time.time()
		self.idvcf = -1
		self.idvariant = 1
		self.current_line = 0
		self.errors = []	
		self.progress = 0

		#check if file exists
		if not os.path.isfile(file):
			#print('[x] File {0} not found'.format(file))
			#return
			sys.exit(100)
		self.file_size = os.path.getsize(file)
		
		#open mysql connection
		self.mysql_connect()
		
		#insert vcf file into db
		#print('[+] Working file: ', file)
		self.insert_vcf_file(file, originalname)
		#read vcf file
		#print('[+] Reading file')
		with open(file, 'rt') as vcf_file:
			for line in vcf_file:
				self.current_line += 1
				self.progress = self.progress + len(line)
				#self.show_progress()
				if line[0]=='#':
					self.parse_meta_line(line)
				else:
					self.parse_record(line)
		#print()
		#self.show_stats()
		self.mysql_close()
		self.end_time = time.time()
		#print('[+] Elapsed time {0:.02f}s'.format(self.end_time-self.start_time))
		
	def get_file_hash(slef, file):
		blocksize=65536
		hasher = hashlib.md5()
		with open(file,'rb') as file_handler:
			buf = file_handler.read(blocksize)
			while len(buf) > 0:
				hasher.update(buf)
				buf = file_handler.read(blocksize)
		return hasher.hexdigest()

	def handle_error(self, error):
		e = '[x] Error in line #{0}: {1}'.format(self.current_line, error)
		self.errors.append(e)
		#print(e)

	def parse_meta_line(self, line):
		try:
			if line[:2] == '##':
				meta = {}
				meta['type'] = ''.join(self.r_meta_type.findall(line))
				meta['id'] = ''.join(self.r_meta_id.findall(line))
				if len(meta['id']) == 0:
					meta['description'] = ''.join(self.r_meta_desc1.findall(line))[:100]					
				else:
					meta['description'] = ''.join(self.r_meta_desc2.findall(line))[:100]
				self.insert_meta_line(meta)
			else:
				self.header = line
		except Exception as e:
			self.handle_error(e)

	def get_variant_type(self, ref, alt):
		# variant types taken from the not so comprehensive list at 
		# http://snpeff.sourceforge.net/SnpEff_manual.html
		# http://resources.qiagenbioinformatics.com/manuals/clcgenomicsworkbench/700/Variant_types.html
		lref = len(ref)
		lalt = len(alt)
		if lref==1 and lalt==1: 
			return 'SNV' # Single-Nucleotide Variant
		return 'INDEL' #Insertion/Deletion

	def parse_record(self, line):
		try:
			columns = line.split('\t')
			if len(columns)<8:
				sys.exit(101)
				#raise Exception('Only {0} columns found. Must have at least 8 columns: CHROM, POS, ID, REF, ALT, QUAL, FILTER, and, INFO.'.format(len(columns)))
			variant = { 'chrom': columns[0], 'pos': columns[1], 'id': columns[2] if columns[2]!='.' else '', 'ref': columns[3], 'alt': columns[4], 'qual': columns[5], 'filter': columns[6], 'idvcf': self.idvcf}
			variant['variant_type'] = self.get_variant_type(columns[3], columns[4])
			self.insert_variant(variant)
			self.parse_record_info(columns[7])
		except Exception as e:
			self.handle_error(e)

	def parse_record_info(self, info):
		self.get_frequencies(info)
		self.get_functional_annotations(info)
		self.get_consequence_annotations(info)
		self.get_clinical_variants(info)
		self.get_predictions(info)
		self.get_refgenes(info)
		self.get_extras(info)

	def get_frequencies(self, info):
		try:
			frequencies = self.r_frequency.findall(info)
			self.insert_frequencies(frequencies)
		except Exception as e:
			#print('error @ get_frequencies')
			self.handle_error(e)

	def get_functional_annotations(self, info):
		try:			
			annotations = self.r_functional.findall(info)[0]
			annotations = annotations.split(',')
			for a in annotations:
				values = a.split('|')
				a = {}
				for idx, field in enumerate(self.functional_annotations_fields):
					a[field] = values[idx]
				self.insert_functional_annotation(a)
		except Exception as e:
			#print('error @ get_functiona_annotations')
			self.handle_error(e)

	def get_consequence_annotations(self, info):
		try:			
			annotations = self.r_consequences.findall(info)[0]
			annotations = annotations.split(',')
			for a in annotations:
				values = a.split('|')
				csq = {}
				for idx, field in enumerate(self.consequence_annotations_fields):
					csq[field] = values[idx]
				self.insert_consequences_annotation(csq)
		except Exception as e:
			#print('error @ get_consequence_annotations')
			self.handle_error(e)

	def get_clinical_variants(self, info):
		try:
			clinvar = self.r_clinvar.findall(info)
			self.insert_clinvar(clinvar)
		except Exception as e:
			#print('error @ get_clinical_variants')
			self.handle_error(e)

	def get_predictions(self, info):
		try:			
			scores =  self.r_scores.findall(info)
			predictions =  self.r_predictions.findall(info)
			self.insert_prediction(scores, predictions)
		except Exception as e:
			#print('error @ get_predictions')
			self.handle_error(e)

	def get_refgenes(self, info):
		try:			
			refgenes =  self.r_refgenes.findall(info)
			self.insert_refgene(refgenes)
		except Exception as e:
			#print('error @ get_refgenes')
			self.handle_error(e)

	def get_extras(self, info):
		try:						
			extras_fields_to_columns = {'name':'NCBI', 'name2':'gene', 'gadAll': 'GAD', 'otherChrom': 'other_chrom', 'positionType': 'position_type', 'transcriptStrand': 'transcript_strand', 'HGNC_GeneAnnotation': 'HGNC', 'functionalClass': 'functional_class'}
			extras = {}
			for field in extras_fields_to_columns:
				match = re.search(r'[\t;]{}=(.*?)[;\n\r]+'.format(field), info)
				if match is None:
					continue
				extras[extras_fields_to_columns[field]] = match.groups(0)[0]
			#print(extras)
			self.insert_extras(extras)
		except Exception as e:
			#print('error @ get_extras')
			self.handle_error(e)

	#mysql insertions

	def insert_vcf_file(self, file, originalname):
		#print('[+] Inserting file header into database.')
		add_vcf_file = 'INSERT INTO vcf_files (filename, filehash) VALUES (%s, %s)'
		data_vcf_file = (os.path.basename(originalname), self.get_file_hash(file))
		self.cursor.execute(add_vcf_file, data_vcf_file)
		self.idvcf = self.cursor.lastrowid
		
	def insert_meta_line(self, meta):
		add_vcf_meta = 'INSERT INTO vcf_meta (`idvcf`, `type`, `id`, `description`) VALUES (%(idvcf)s, %(type)s, %(id)s, %(description)s)'
		meta['idvcf'] = self.idvcf
		self.cursor.execute(add_vcf_meta, meta)

	def insert_variant(self, variant):
		values = ','.join(['%({0})s'.format(key) for key in variant])
		columns = ','.join(variant)
		add_variant = 'INSERT INTO variants ('+columns+') VALUES ('+values+')'		
		self.cursor.execute(add_variant, variant)
		self.idvariant = self.cursor.lastrowid
		
	def insert_functional_annotation(self, annotation):
		annotation['idvariant'] = self.idvariant
		fields = self.functional_annotations_fields+['idvariant']
		values = ','.join(['%({0})s'.format(field) for field in fields])
		columns = ','.join(fields)
		add_annotation = 'INSERT INTO functional_annotations ('+columns+') VALUES ('+values+')'		
		self.cursor.execute(add_annotation, annotation)

	def insert_consequences_annotation(self, consequences):
		consequences['idvariant'] = self.idvariant
		fields = self.consequence_annotations_fields+['idvariant']
		values = ','.join(['%({0})s'.format(v) for v in fields])
		columns = ','.join(fields)
		add_consequence = 'INSERT INTO consequence_annotations ('+columns+') VALUES ('+values+')'		
		self.cursor.execute(add_consequence, consequences)

	def insert_frequencies(self, frequencies):
		frequencies = [(f[0], float(f[1])) for f in frequencies if f[1]!='.']
		if len(frequencies)==0:
			return
		frequencies = frequencies+[('idvariant',self.idvariant)]
		frequencies = dict(frequencies)
		values = ','.join(['%({0})s'.format(key) for key in frequencies])
		columns = ','.join(['`{0}`'.format(key) for key in frequencies])
		add_frequency = 'INSERT INTO frequencies ('+columns+') VALUES ('+values+')'
		self.cursor.execute(add_frequency, frequencies)

	def insert_clinvar(self, clinvar):
		if all(c[1]=='.' for c in clinvar):
			return
		clinvar = [(c[0], c[1]) for c in clinvar if c[1]!='.']
		clinvar = clinvar+[('idvariant',self.idvariant)]
		clinvar = dict(clinvar)
		values = ','.join(['%({0})s'.format(key) for key in clinvar])
		columns = ','.join(['`{0}`'.format(key) for key in clinvar])
		add_clinvar = 'INSERT INTO clinical_variants ('+columns+') VALUES ('+values+')'

		#get nested data as independent records (ex: CLINSIG:benign|other)
		for c in ['CLINSIG','CLNDBN','CLNACC','CLNDSDB','CLNDSDBID']:
			clinvar[c] = clinvar[c].split('|')

		for i in range(0, len(clinvar['CLINSIG'])):
			data = {}			
			for c in clinvar:
				if(c in ['CLINSIG','CLNDBN','CLNACC','CLNDSDB','CLNDSDBID']):
					data[c] = clinvar[c][i]
				else:
					data[c] = clinvar[c]
			self.cursor.execute(add_clinvar, data)

	def insert_prediction(self, scores, preds):
		add_prediction = 'INSERT INTO predictions (`idvariant`,`score`,`prediction`,`type`) VALUES (%(idvariant)s, %(score)s, %(prediction)s, %(type)s)'
		for score, pred in zip(scores, preds):
			if score[1] != '.' or pred[1]!='.':
				p = {'idvariant': self.idvariant, 'score': score[1],'prediction': pred[1], 'type': score[0]}
				self.cursor.execute(add_prediction, p)

	def insert_refgene(self, refgene):
		refgenes = [(r[0], r[1]) for r in refgene if r[1]!='.']
		if len(refgenes)==0:
			return
		refgenes = refgenes+[('idvariant',self.idvariant)]
		refgenes = dict(refgenes)
		values = ','.join(['%({0})s'.format(key) for key in refgenes])
		columns = ','.join(['`{0}`'.format(key) for key in refgenes])
		add_refgene = 'INSERT INTO ref_genes ('+columns+') VALUES ('+values+')'
		self.cursor.execute(add_refgene, refgenes)

	def insert_extras(self, extras):
		extras['idvariant'] = self.idvariant
		values = ','.join(['%({0})s'.format(key) for key in extras])
		columns = ','.join(['`{0}`'.format(key) for key in extras])
		add_extras = 'INSERT INTO extras ('+columns+') VALUES ('+values+')'
		self.cursor.execute(add_extras, extras)

	def show_stats(self):
		print('[+] Collecting results...')
		id = {'idvcf':self.idvcf}
		self.cursor.execute('SELECT COUNT(1) FROM vcf_meta WHERE idvcf = %(idvcf)s', id)
		metadata = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM variants AS v WHERE v.idvcf = %(idvcf)s', id)
		variants = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM functional_annotations AS fa, variants AS v WHERE fa.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		ann = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM predictions AS p, variants AS v WHERE p.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		scores = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM consequence_annotations AS csq, variants AS v WHERE csq.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		csq = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM clinical_variants AS c, variants AS v WHERE c.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		clinvar = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM frequencies AS f, variants AS v WHERE f.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		freqs = self.cursor.fetchone()[0]
		self.cursor.execute('SELECT COUNT(1) FROM ref_genes AS r, variants AS v WHERE r.idvariant = v.idvariant AND v.idvcf = %(idvcf)s', id)
		refgen = self.cursor.fetchone()[0]
		
		print('[+] VCF details:')
		self.cursor.execute('SELECT idvcf, filename, filehash FROM vcf_files WHERE idvcf = {0}'.format(self.idvcf))
		(idvcf, filename, filehash) = self.cursor.fetchone()
		print('\t Impoted file:',filename)
		print('\t File hash:',filehash)
		print('\t File ID:',idvcf)
		print('\t Lines processed:',self.current_line)
		print('[+] Insertion results:')		
		print('\t Metadata ', metadata)
		print('\t Variants ', variants)		
		print('\t Functional Annotations ', ann)		
		print('\t Predictions/Scores ', scores)		
		print('\t Consequence Annotations ', csq)		
		print('\t Clinical variants ', clinvar)		
		print('\t Frequencies ', freqs)		
		print('\t ref.Gene ', refgen)

		print('[+] Finish!')

	def mysql_connect(self):
		#check if config file exists
		config_file = '/home/ubuntu/workspace/processor/config.json'
		if not os.path.isfile(config_file):
			#print('[x] Missing config file {0}.'.format(config_file))
			#print('[x] Example: \n{\n\t"user": "root",\n\t"password": "p4sW0rD",\n\t"host": "127.0.0.1",\n\t"database": "bio"\n}\n')
			sys.exit(200)
		cfg = {}
		with open(config_file) as config_handler:    
			cfg = json.load(config_handler)
		#mysql configuration
		self.mysql_config['user'] = cfg['user']
		self.mysql_config['password'] = cfg['password']
		self.mysql_config['host'] = cfg['host']
		self.mysql_config['database'] = cfg['database']
		#print('[+] MySQL: connected to host "{}" using database "{}"'.format(cfg['host'], cfg['database']))
		self.cnx = mysql.connector.connect(**self.mysql_config)		
		self.cursor = self.cnx.cursor()

	def mysql_close(self):
		self.cnx.commit()
		self.cursor.close()

if __name__ == '__main__':
	originalname = sys.argv[1]
	filename = sys.argv[2]
	vcf = VCF(filename, originalname)
	print(vcf.idvcf)
	sys.exit(0)